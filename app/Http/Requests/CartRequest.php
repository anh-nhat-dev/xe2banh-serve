<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id'  => 'required|min:1|integer',
            'qty' => 'min:1|integer',
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            'id.required' => __('Product ID is required'),
            'id.integer'  => __('Product ID must be a number'),
        ];
    }
}
