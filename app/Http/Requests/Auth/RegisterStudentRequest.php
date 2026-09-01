<?php

namespace App\Http\Requests\Auth;

use App\Enums\GuardianRelationshipType;
use App\Models\AcademicTerm;
use App\Rules\EgyptianMobileNumber;
use App\Models\SubscriptionPlan;
use App\Services\CommercePricingService;
use App\Services\ManualPaymentSettingsService;
use App\Services\PlatformSettingsService;
use App\Support\PhoneNumbers\EgyptianPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        // Let the controller return the stable maintenance response before validating a disabled form.
        if (! app(PlatformSettingsService::class)->get('registration_enabled')) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'student_phone' => [
                'required',
                new EgyptianMobileNumber,
                Rule::unique('users', 'phone_e164'),
            ],
            'parent_phone' => ['required', 'different:student_phone', new EgyptianMobileNumber],
            'grade' => ['required', Rule::in(['grade-1', 'grade-2'])],
            'semester' => ['required', Rule::in(['semester-1', 'semester-2'])],
            'relationship_type' => ['required', Rule::enum(GuardianRelationshipType::class)],
            'relationship_details' => ['nullable', 'required_if:relationship_type,other', 'prohibited_unless:relationship_type,other', 'string', 'min:2', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'promo_code' => ['nullable', 'string', 'max:100'],
            'payment_channel' => ['nullable', Rule::in(array_keys(array_filter(
                app(ManualPaymentSettingsService::class)->channels(),
                fn (array $channel) => filled($channel['destination'])
            )))],
            'transaction_id' => ['nullable', 'string', 'min:4', 'max:150', 'unique:manual_payment_submissions,transaction_id'],
            'sender_identifier' => ['nullable', 'string', 'min:4', 'max:100'],
            'transferred_at' => ['nullable', 'date', 'before_or_equal:now', 'after:'.now()->subDays(7)->toDateTimeString()],
            'receipt' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:5120'],
            'role' => ['prohibited'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->has('subscription_plan_id') || $validator->errors()->has('promo_code')) {
                return;
            }

            $plan = SubscriptionPlan::whereKey($this->input('subscription_plan_id'))->where('is_active', true)->first();
            if (! $plan) {
                return;
            }

            if (! $validator->errors()->has('grade') && ! $validator->errors()->has('semester')) {
                $hasMatchingTerm = AcademicTerm::active()
                    ->where('code', $this->input('semester'))
                    ->whereHas('grade', fn ($query) => $query->where('code', $this->input('grade')))
                    ->whereHas('subscriptionPlans', fn ($query) => $query->whereKey($plan->id))
                    ->exists();

                if (! $hasMatchingTerm) {
                    $validator->errors()->add('subscription_plan_id', 'The selected subscription plan is not available for this grade and semester.');
                    return;
                }
            }

            try {
                $quote = app(CommercePricingService::class)->quoteSubscriptionPlan($plan, $this->input('promo_code'));
            } catch (\Throwable) {
                $validator->errors()->add('promo_code', 'The promo code is invalid or does not apply to this plan.');
                return;
            }

            if ((float) $quote['total'] <= 0.001) {
                return;
            }

            foreach ([
                'payment_channel' => 'Choose a payment method.',
                'transaction_id' => 'Enter the transaction ID.',
                'sender_identifier' => 'Enter the sender number or account.',
                'transferred_at' => 'Choose the transfer time.',
                'receipt' => 'Attach the transfer receipt.',
            ] as $field => $message) {
                if (! $this->hasFile($field) && blank($this->input($field))) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['student_phone', 'parent_phone'] as $field) {
            $phone = EgyptianPhoneNumber::normalize($this->input($field));
            if ($phone !== null) {
                $normalized[$field] = $phone;
            }
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('relationship_details'))) {
            $normalized['relationship_details'] = trim($this->input('relationship_details'));
        }
        if (is_string($this->input('promo_code'))) {
            $normalized['promo_code'] = mb_strtoupper(trim($this->input('promo_code')));
        }

        $this->merge($normalized);
    }
}
