<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'name' => 'string',
            'brand_id' => 'exists:brands,id',
            'category_id' => 'exists:categories,id',
            'sku' => 'unique:products,sku,' . $this->route('product'),
            'unit_of_measurement' => 'string',
            'cost_price' => 'numeric',
            'selling_price' => 'numeric',
            'expiry_date' => 'nullable|date',
            'trackable' => 'boolean',
        ];
    }
}
