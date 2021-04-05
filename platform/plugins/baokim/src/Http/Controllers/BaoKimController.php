<?php

namespace Botble\BaoKim\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Botble\Payment\Services\Traits\PaymentTrait;
use Botble\BaoKim\BaoKimAPI;

class BaoKimController extends BaseController
{
    use PaymentTrait;

    /**
     * 
     */
    public function paymentCallback(Request $request)
    {

        $checksum = $request->input('checksum');
        $query = $request->except((["checksum", "your_param"]));
        ksort($query);
        $verifyQuery = app(BaoKimAPI::class)->verifyQuery($query, $checksum);

        if ($verifyQuery) {
            // $this->storeLocalPayment([
            //     'amount'          => $result->amount->value,
            //     'currency'        => $result->amount->currency,
            //     'charge_id'       => $result->id,
            //     'payment_channel' => MOLLIE_PAYMENT_METHOD_NAME,
            //     'status'          => $status,
            //     'customer_id'     => auth('customer')->check() ? auth('customer')->user()->getAuthIdentifier() : null,
            //     'payment_type'    => 'direct',
            //     'order_id'        => $result->metadata->order_id,
            // ]);
        }
    }
}
