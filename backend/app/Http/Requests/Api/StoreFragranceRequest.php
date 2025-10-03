<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreFragranceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:255'],
            'brand_name_en' => ['nullable', 'string', 'max:255'],
            'fragrance_name' => ['required', 'string', 'max:255'],
            'fragrance_name_en' => ['nullable', 'string', 'max:255'],
            'volume_ml' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_place' => ['nullable', 'string', 'max:255'],
            'possession_type' => ['required', 'string', 'in:full_bottle,decant,sample'],
            'user_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:48'],
            'projection' => ['nullable', 'string', 'in:weak,moderate,strong'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:100'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand_name' => __('fragrance.brand_name'),
            'brand_name_en' => __('fragrance.brand_name_en'),
            'fragrance_name' => __('fragrance.fragrance_name'),
            'fragrance_name_en' => __('fragrance.fragrance_name_en'),
            'volume_ml' => __('fragrance.volume'),
            'purchase_price' => __('fragrance.purchase_price'),
            'purchase_date' => __('fragrance.purchase_date'),
            'purchase_place' => __('fragrance.purchase_place'),
            'possession_type' => __('fragrance.possession_type'),
            'user_rating' => __('fragrance.rating'),
            'comments' => __('fragrance.comments'),
            'duration_hours' => __('fragrance.duration_hours'),
            'projection' => __('fragrance.projection'),
            'tags' => __('fragrance.tags'),
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
            'brand_name.required' => __('validation.required', ['attribute' => __('fragrance.brand_name')]),
            'fragrance_name.required' => __('validation.required', ['attribute' => __('fragrance.fragrance_name')]),
            'possession_type.required' => __('validation.required', ['attribute' => __('fragrance.possession_type')]),
            'possession_type.in' => __('validation.in', ['attribute' => __('fragrance.possession_type')]),
            'purchase_date.before_or_equal' => __('validation.before_or_equal', ['attribute' => __('fragrance.purchase_date'), 'date' => __('common.today')]),
            'user_rating.between' => __('validation.between.numeric', ['attribute' => __('fragrance.rating'), 'min' => 1, 'max' => 5]),
            'tags.max' => __('validation.max.array', ['attribute' => __('fragrance.tags'), 'max' => 10]),
        ];
    }
}
