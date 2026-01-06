<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferInventoryRequest extends FormRequest
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
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id' => 'required|exists:units,id|different:from_unit_id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
