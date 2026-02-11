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
            'sku' => 'unique:products,sku,' . $this->route('product'),
            'unit_of_measurement' => 'string',
            'cost_price' => 'numeric',
            'selling_price' => 'numeric',
            'expiry_date' => 'nullable|date',
            'trackable' => 'boolean',
            'product_type' => 'in:set,individual',
            'items_per_set' => 'nullable|integer|min:1',
            'size' => 'nullable|in:small,medium,big',
            'source_type' => 'in:central_stock,unit_produced',
        ];
    }
}
