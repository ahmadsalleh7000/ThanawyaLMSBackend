<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\RegisterStudentAction;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterStudentRequest;
use App\Http\Requests\Auth\SetForcedPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Rules\EgyptianMobileNumber;
use App\Services\CommercePricingService;
use App\Services\LoginEventService;
use App\Services\PlatformSettingsService;
use App\Support\PhoneNumbers\EgyptianPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        RegisterStudentRequest $request,
        PlatformSettingsService $settings,
        RegisterStudentAction $registerStudent,
    ): JsonResponse {
        abort_unless($settings->get('registration_enabled'), 403, 'New account registration is temporarily disabled.');
        $result = $registerStudent->execute($request->validated());

        $approved = $result->paymentSubmission === null;

        return response()->json([
            'message' => $approved
                ? 'Your registration was approved with a full discount promo code.'
                : 'Your registration and payment receipt are awaiting administrator review.',
            'application' => [
                'status' => $approved ? RegistrationStatus::Approved->value : RegistrationStatus::PendingPaymentReview->value,
                'reference' => $result->order->public_id,
            ],
        ], 201);
    }

    public function validateRegistrationField(Request $request, PlatformSettingsService $settings): JsonResponse
    {
        abort_unless($settings->get('registration_enabled'), 403, 'New account registration is temporarily disabled.');

        $field = (string) $request->input('field');
        abort_unless(in_array($field, ['email', 'student_phone', 'parent_phone', 'transaction_id'], true), 422, 'Unsupported validation field.');

        $data = ['value' => $request->input('value')];
        if ($field === 'email' && is_string($data['value'])) {
            $data['value'] = mb_strtolower(trim($data['value']));
        }
        if (in_array($field, ['student_phone', 'parent_phone'], true)) {
            $phone = EgyptianPhoneNumber::normalize($data['value']);
            if ($phone !== null) {
                $data['value'] = $phone;
            }
        }

        $rules = match ($field) {
            'email' => ['value' => ['required', 'email', 'max:255', Rule::unique('users', 'email')]],
            'student_phone' => ['value' => ['required', new EgyptianMobileNumber, Rule::unique('users', 'phone_e164')]],
            'parent_phone' => ['value' => ['required', new EgyptianMobileNumber]],
            'transaction_id' => ['value' => ['required', 'string', 'min:4', 'max:150', Rule::unique('manual_payment_submissions', 'transaction_id')]],
        };

        if ($field === 'parent_phone') {
            $studentPhone = EgyptianPhoneNumber::normalize($request->input('student_phone'));
            if ($studentPhone !== null && $studentPhone === $data['value']) {
                throw ValidationException::withMessages(['value' => ['The guardian phone must be different from the student phone.']]);
            }
        }

        Validator::make($data, $rules)->validate();

        return response()->json(['data' => ['field' => $field, 'valid' => true]]);
    }

    public function quoteRegistrationPromo(Request $request, PlatformSettingsService $settings, CommercePricingService $pricing): JsonResponse
    {
        abort_unless($settings->get('registration_enabled'), 403, 'New account registration is temporarily disabled.');

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'promo_code' => ['required', 'string', 'max:100'],
        ]);

        $plan = SubscriptionPlan::whereKey($validated['subscription_plan_id'])->where('is_active', true)->firstOrFail();
        $quote = $pricing->quoteSubscriptionPlan($plan, $validated['promo_code']);

        return response()->json(['data' => [
            'code' => $quote['coupon']->code,
            'type' => $quote['coupon']->type,
            'value' => (float) $quote['coupon']->value,
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'currency' => $plan->currency,
        ]]);
    }

    public function login(LoginRequest $request, LoginEventService $events): JsonResponse
    {
        $identity = trim((string) ($request->validated('identity') ?: $request->validated('email')));
        $errorField = $request->filled('identity') ? 'identity' : 'email';
        $phone = EgyptianPhoneNumber::normalize($identity);
        $user = User::query()->where(function ($query) use ($identity, $phone): void {
            $query->where('email', mb_strtolower($identity));
            if ($phone) {
                $query->orWhere('phone_e164', $phone);
            }
        })->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            $events->record($request, $user, $identity, 'sanctum', false, 'invalid_credentials');
            throw ValidationException::withMessages([$errorField => ['The provided credentials are incorrect.']]);
        }
        if ($user->isAdmin()) {
            $events->record($request, $user, $user->email, 'sanctum', false, 'admin_portal_required');
            throw ValidationException::withMessages([$errorField => ['Administrator accounts must use the Laravel admin portal.']]);
        }
        if ($user->must_change_password && $user->temporary_password_expires_at?->isPast()) {
            $events->record($request, $user, $identity, 'sanctum', false, 'temporary_password_expired');
            throw ValidationException::withMessages([$errorField => ['Your temporary password has expired. Contact the academy to reissue your guardian invitation.']]);
        }
        $paymentOnly = $user->role === User::ROLE_STUDENT
            && $user->registrationStatus() === RegistrationStatus::PendingPaymentReview;
        if (! $user->isEligibleForAccess() && ! $paymentOnly) {
            $events->record($request, $user, $user->email, 'sanctum', false, 'inactive_account');
            $message = match ($user->registrationStatus()) {
                RegistrationStatus::PendingPaymentReview => 'Your registration and payment are still under review.',
                RegistrationStatus::Rejected => 'Your registration was not approved. Contact support for details.',
                default => 'This account is inactive. Contact support.',
            };
            throw ValidationException::withMessages([$errorField => [$message]]);
        }

        $events->record($request, $user, $user->email, 'sanctum', true);
        $user->setAttribute('payment_only', $paymentOnly);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken($request->string('device_name', 'web')->toString())->plainTextToken,
            'paymentOnly' => $paymentOnly,
        ]);
    }

    public function setForcedPassword(SetForcedPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->temporary_password_expires_at?->isPast(), 422, 'The temporary password has expired. Ask the academy to reissue your invitation.');
        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'password_changed_at' => now(),
        ]);
        $user->tokens()->whereKeyNot($request->user()->currentAccessToken()?->id)->delete();

        return response()->json(['message' => 'Password updated successfully.', 'user' => $user->fresh()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $details = $request->validated();

        $emailChanged = $details['email'] !== $user->email;
        $user->update($details);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
