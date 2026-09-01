<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        $arabic = str_starts_with((string) $this->header('Accept-Language', $this->input('locale', 'en')), 'ar');

        if (! $arabic) {
            return [
                'name.required' => 'Enter your full name.',
                'name.min' => 'Enter your full name.',
                'email.required' => 'Enter your email address.',
                'email.email' => 'Enter a valid email address.',
                'subject.required' => 'Enter the message subject.',
                'subject.min' => 'The subject must be at least 4 characters.',
                'message.required' => 'Write your message.',
                'message.min' => 'The message must be at least 20 characters.',
                'website.max' => 'We could not send the message. Please refresh the page and try again.',
            ];
        }

        return [
            'name.required' => 'اكتب اسمك الكامل.',
            'name.min' => 'اكتب اسمك الكامل.',
            'email.required' => 'اكتب بريدك الإلكتروني.',
            'email.email' => 'اكتب بريداً إلكترونياً صحيحاً.',
            'subject.required' => 'اكتب موضوع الرسالة.',
            'subject.min' => 'يجب ألا يقل الموضوع عن 4 أحرف.',
            'message.required' => 'اكتب رسالتك.',
            'message.min' => 'يجب ألا تقل الرسالة عن 20 حرفاً.',
            'website.max' => 'تعذر إرسال الرسالة. حدّث الصفحة ثم حاول مرة أخرى.',
        ];
    }
}
