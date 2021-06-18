<?php

namespace Botble\BaoKim\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Services\Traits\PaymentTrait;
use Botble\BaoKim\BaoKimAPI;
use Botble\Payment\Repositories\Interfaces\PaymentInterface;
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
        $orderId  = $request->input('order_id');
        $query = $request->except((["checksum", "order_id"]));
        $verifyQuery = app(BaoKimAPI::class)->verifyQuery($query, $checksum);
        ksort($query);

        $responseQuery = http_build_query([
            "_token" => $token
        ]);

        if ($orderId && $verifyQuery) {
            $orderDetail = app(BaoKimAPI::class)->getOrderDetail($request->input('id'), $request->input('mrc_order_id'));
            $status = PaymentStatusEnum::PENDING;

            $data = $orderDetail->data;
    
            switch ($request->input("stat")) {
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


            app(PaymentInterface::class)->update(
            [
                'order_id'        => $orderId,
                'charge_id'       => $data->id ?? $request->input("id")
            ],
            [
                'status'          => $status,
            ], );

            if ($request->input("stat") != "c") {
                return $response
                    ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-that-bai.html?' . $responseQuery);
            }

            return $response
                ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-thanh-cong.html?' . $responseQuery);
        }
        return $response
            ->setNextUrl(env('FE_SITE_URL', url('/')) . '/dat-hang-that-bai.html?' . $responseQuery);
    }

    /**
     * 
     */
    public function webHook(Request $request, BaseHttpResponse $response){

        $request;
        return $response->setData(["err_code" => 0]);
    }
}
