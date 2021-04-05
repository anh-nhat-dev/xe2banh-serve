<?php

namespace App\Http\Requests;

use Botble\Ecommerce\Enums\ShippingMethodEnum;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class OrderRequest extends Request
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

        $rules = [
            'payment_method'  => 'required|' . Rule::in(PaymentMethodEnum::values()),
            'shipping_method' => 'required|' . Rule::in(ShippingMethodEnum::values()),
            'amount'          => 'required|min:0',
        ];

        // $rules['address.address_id'] = 'required_without:address.name';
        if (!$this->has('address.address_id') || $this->input('address.address_id') === 'new') {
            $rules['address.name'] = 'required|min:3|max:120';
            $rules['address.phone'] = 'required|numeric';
            $rules['address.email'] = 'required|email';
            $rules['address.state'] = 'required';
            $rules['address.city'] = 'required';
            $rules['address.address'] = 'required|string';
        }

        if ($this->input('create_account') == 1) {
            $rules['password'] = 'required|min:6';
            $rules['password_confirmation'] = 'required|same:password';
            $rules['address.email'] = 'required|max:60|min:6|email|unique:ec_customers,email';
            $rules['address.name'] = 'required|min:3|max:120';
        }

        if  ($this->input("payment_method") == "baokim") {
            $rules['bao_kim_bank'] = 'required|numeric';
        }

        return $rules;
    }

    /**
     * @return array
     */
    public function messages()
    {
        $messages = [
            'address.name.required'    => __("Trường tên là bắt buộc."),
            'address.phone.required'   => __("Trường số điện thoại là bắt buộc."),
            'address.email.required'   => __("Trường email là bắt buộc."),
            'address.email.unique'     => __("Khách hàng có email đó đã tồn tại, vui lòng chọn email khác hoặc đăng nhập bằng email này!"),
            'address.state.required'   => __("Trường trạng thái là bắt buộc."),
            'address.city.required'    => __('Trường thành phố là bắt buộc.'),
            'address.address.required' => __('Trường địa chỉ là bắt buộc.'),
            'bao_kim_bank.required'    => __("Bạn chưa chọn hình thức  thanh toán của Bảo Kim")
        ];

        return array_merge(parent::messages(), $messages);
    }
}
