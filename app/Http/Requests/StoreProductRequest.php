<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'name' => 'required|string',
            'brand_id' => 'required|exists:brands,id',
            'sku' => 'required|unique:products|string',
            'unit_of_measurement' => 'required|string',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'expiry_date' => 'nullable|date',
            'trackable' => 'boolean',
            'product_type' => 'nullable|in:set,individual',
            'size' => 'nullable|in:small,medium,big',
            'items_per_set' => 'required_if:product_type,set|integer|min:1',
            'source_type' => 'nullable|in:central_stock,unit_produced',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
