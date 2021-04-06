<?php

namespace Botble\BaoKim\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Services\Traits\PaymentTrait;
use Botble\BaoKim\BaoKimAPI;
use Illuminate\Support\Str;
use Botble\Base\Http\Responses\BaseHttpResponse;
use OrderHelper;

class BaoKimController extends BaseController
{
    use PaymentTrait;

    /**
     * 
     */
    public function paymentCallback($token, Request $request, BaseHttpResponse $response)
    {
        $checksum = $request->input('checksum');
        $query = $request->except((["checksum", "your_param"]));
        $verifyQuery = app(BaoKimAPI::class)->verifyQuery($query, $checksum);
        ksort($query);

        $responseQuery = http_build_query([
            "_token" => $token
        ]);

        if ($verifyQuery) {
            $orderDetail    = app(BaoKimAPI::class)->getOrderDetail($request->input('id'), $request->input('mrc_order_id'));
            $data = $orderDetail->data;
            if ($orderDetail->code != 0) return $response
                ->setError()
                ->setNextUrl(env('FE_SITE_URL', url('/')) . '/thanh-toan-that-bai.html?' . $responseQuery);

            $prefix = get_ecommerce_setting('store_order_prefix') ? get_ecommerce_setting('store_order_prefix') . '-' : '';
            $suffix = get_ecommerce_setting('store_order_suffix') ? '-' . get_ecommerce_setting('store_order_suffix') : '';
            $orderId    =   (int)Str::replaceFirst($suffix, "", Str::replaceFirst($prefix, "", Str::replaceFirst("#", "", $request->input('mrc_order_id')))) - (int)config('plugins.ecommerce.order.default_order_start_number');

            $status = PaymentStatusEnum::PENDING;

            switch ($data->stat) {
                case "p":
                case "r":
                    $status = PaymentStatusEnum::PENDING;
                    break;
                case "c":
                    $status = PaymentStatusEnum::COMPLETED;
                    break;
                case "d":
                    $status = PaymentStatusEnum::FAILED;
                    break;
            }

            $this->storeLocalPayment([
                'amount'          => $data->total_amount,
                'currency'        => "VND",
                'charge_id'       => $data->id,
                'payment_channel' => BAOKIM_PAYMENT_METHOD_NAME,
                'status'          => $status,
                'customer_id'     => auth('customer')->check() ? auth('customer')->user()->getAuthIdentifier() : null,
                'payment_type'    => $data->bpm_id,
                'order_id'        => $orderId,
            ]);

            OrderHelper::processOrder($orderId, $data->id);

            if ($data->stat != "c") {
                return $response
                    ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-that-bai.html?' . $responseQuery);
            }

            return $response
                ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-thanh-cong.html?' . $responseQuery);
        }
        return $response
            ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-that-bai.html?' . $responseQuery);
    }
}
