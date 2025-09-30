<?php

namespace App\Http\Requests\Api\AI;

use Illuminate\Foundation\Http\FormRequest;

class SmartFragranceInputRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'min:2', 'max:300'],
            'provider' => ['sometimes', 'string', 'in:openai,anthropic,gemini'],
            'language' => ['sometimes', 'string', 'in:ja,en,mixed'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'input.required' => __('validation.required', ['attribute' => __('ai.input')]),
            'input.min' => __('validation.min.string', ['attribute' => __('ai.input'), 'min' => 2]),
            'input.max' => __('validation.max.string', ['attribute' => __('ai.input'), 'max' => 300]),
            'provider.in' => __('validation.in', ['attribute' => __('ai.provider')]),
            'language.in' => __('validation.in', ['attribute' => __('ai.language')]),
        ];
    }
}
