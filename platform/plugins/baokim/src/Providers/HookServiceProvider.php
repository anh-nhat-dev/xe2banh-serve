<?php

namespace Botble\BaoKim\Providers;

use Botble\Payment\Enums\PaymentMethodEnum;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Botble\BaoKim\BaoKimAPI;
// use Mollie;
// use OrderHelper;
// use Throwable;


class HookServiceProvider extends ServiceProvider
{
    public function boot()
    {

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, [$this, 'registerBaokimMethod'], 60, 2);
        $this->app->booted(function () {
            add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, [$this, 'checkoutWithMollie'], 17, 2);
        });

        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, [$this, 'addPaymentSettings'], 99);

        add_filter(BASE_FILTER_ENUM_ARRAY, function ($values, $class) {
            if ($class == PaymentMethodEnum::class) {
                $values['BAOKIM'] = BAOKIM_PAYMENT_METHOD_NAME;
            }

            return $values;
        }, 23, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == BAOKIM_PAYMENT_METHOD_NAME) {
                $value = 'Bảo Kim';
            }

            return $value;
        }, 23, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == BAOKIM_PAYMENT_METHOD_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )
                    ->toHtml();
            }

            return $value;
        }, 23, 2);
    }


    /**
     * @param string $html
     * @param array $data
     * @return string
     */
    public function registerBaokimMethod($html, array $data)
    {
        return $html . view('plugins/baokim::method', $data)->render();
    }


    /**
     * @param string $settings
     * @return string
     * @throws Throwable
     */
    public function addPaymentSettings($settings)
    {
        return $settings . view('plugins/baokim::settings')->render();
    }


    /**
     * @param Request $request
     * @param array $data
     */
    public function checkoutWithMollie(array $data, Request $request)
    {
        if ($request->input('payment_method') == BAOKIM_PAYMENT_METHOD_NAME) {

            $payload = array(
                "mrc_order_id"      => random_bytes(2) . $request->input('order_id'),
                "total_amount"      => $request->input("amount"),
                "description"       => 'Order #' . $request->input('order_id'),
                "url_success"       => "http://localhost:8000",
                "customer_email"    => $request->input('address.email'),
                "accept_bank"       => 1,
                "accept_cc"         => 1,
                "accept_qrpay"      => 1,
                "accept_e_wallet"   => 1,
                "customer_phone"    => $request->input('address.phone'),
                "customer_name"     => $request->input('address.name'),
                "customer_address"  => $request->input('address.address')
            );

            $response = app(BaoKimAPI::class)->sendOrder($payload);

            $info = [];

            $info["error"]          = $response->code != 0;
            $info["message"]        = $response->message ?? null;
            $info["charge_id"]      = $response->data->order_id ?? null;
            $info["payment_url"]    = $response->data->payment_url ?? null;

            return $info;
        }

        return $data;
    }
}
