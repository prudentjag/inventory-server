<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
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
            'unit_id' => 'required|exists:units,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer',
            'sets' => 'nullable|integer|min:0',
            'items' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer'
        ];
    }
}
