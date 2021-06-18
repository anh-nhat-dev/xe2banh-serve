<?php

namespace Botble\BaoKim\Providers;

use Botble\Payment\Enums\PaymentMethodEnum;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Botble\BaoKim\BaoKimAPI;
use Botble\Payment\Repositories\Interfaces\PaymentInterface;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Payment\Enums\PaymentStatusEnum;
use OrderHelper;
// use Throwable;


class HookServiceProvider extends ServiceProvider
{
    public function boot()
    {

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, [$this, 'registerBaokimMethod'], 60, 2);
        $this->app->booted(function () {
            add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, [$this, 'checkoutWithBaokim'], 17, 2);
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
    public function checkoutWithBaokim(array $data, Request $request)
    {
        if ($request->input('payment_method') == BAOKIM_PAYMENT_METHOD_NAME) {
            $payload = array(
                "mrc_order_id"          => rand(5, 30) . get_order_code($request->input('order_id')),
                "total_amount"          => config("plugins.baokim.baokim.account_confirm") == "no" ? 30000 : $request->input("amount"),
                "description"           => 'Thanh toán cho đơn hàng ' . get_order_code($request->input('order_id')),
                "url_success"           => route("baokim.payment.callback", ["id" => OrderHelper::getOrderSessionToken(), "order_id" => $request->input('order_id')]),
                "url_detail"            => route("baokim.payment.callback", ["id" => OrderHelper::getOrderSessionToken(), "order_id" => $request->input('order_id')]),
                "customer_email"        => $request->input('address.email'),
                "customer_phone"        => $request->input('address.phone'),
                "customer_name"         => $request->input('address.name'),
                "customer_address"      => $request->input('address.address'). ','.$request->input('address.city') . ','. $request->input('address.state'),
                "merchant_id"           => config('plugins.baokim.baokim.merchant_id'),
                "bpm_id"                => $request->input('bpm_id'),
                // "webhooks"              => route("baokim.payment.webhook", ["order_id" => $request->input('order_id')])
            );

            $response = app(BaoKimAPI::class)->sendOrder($payload);

            $info = [];

            $info["error"]          = $response->code != 0;
            $info["message"]        = $response->message ?? null;
            $info["charge_id"]      = $response->data->order_id ?? null;
            $info["payment_url"]    = $response->data->payment_url ?? null;

            if ($info["charge_id"]) {
                $status = PaymentStatusEnum::PENDING;
                app(PaymentInterface::class)->create([
                    'amount'          => $payload["total_amount"],
                    'currency'        => "VND",
                    'charge_id'       => $info["charge_id"],
                    'payment_channel' => BAOKIM_PAYMENT_METHOD_NAME,
                    'status'          => $status,
                    'order_id'        => $request->input('order_id'),
                ]);

                OrderHelper::processOrder($request->input('order_id'), $info["charge_id"]);
            }
            
            
            return $info;
        }

        return $data;
    }
}
