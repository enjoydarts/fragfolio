<?php

namespace App\Http\Requests\Api\AI;

use Illuminate\Foundation\Http\FormRequest;

class NormalizeFragranceRequest extends FormRequest
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
            'brand_name' => ['required', 'string', 'min:2', 'max:100'],
            'fragrance_name' => ['required', 'string', 'min:2', 'max:200'],
            'provider' => ['sometimes', 'string', 'in:openai,anthropic,gemini'],
            'language' => ['sometimes', 'string', 'in:ja,en'],
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
            'brand_name.required' => __('validation.required', ['attribute' => __('ai.brand_name')]),
            'brand_name.min' => __('validation.min.string', ['attribute' => __('ai.brand_name'), 'min' => 2]),
            'brand_name.max' => __('validation.max.string', ['attribute' => __('ai.brand_name'), 'max' => 100]),
            'fragrance_name.required' => __('validation.required', ['attribute' => __('ai.fragrance_name')]),
            'fragrance_name.min' => __('validation.min.string', ['attribute' => __('ai.fragrance_name'), 'min' => 2]),
            'fragrance_name.max' => __('validation.max.string', ['attribute' => __('ai.fragrance_name'), 'max' => 200]),
            'provider.in' => __('validation.in', ['attribute' => __('ai.provider')]),
            'language.in' => __('validation.in', ['attribute' => __('ai.language')]),
        ];
    }
}
