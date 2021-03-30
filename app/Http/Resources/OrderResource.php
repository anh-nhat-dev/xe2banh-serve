<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "order_number"          => get_order_code($this->id),
            "products"              => collect($this->products)->map(function ($item) {
                $item["product"] = new ProductResource($item->product);
                $attributes = get_product_attributes($item->product_id);
                $att  = "";

                if (!empty($attributes)) :
                    foreach ($attributes as $attribute) :
                        if ($attribute->attribute_set_slug == "color") :
                            $att .= $attribute->attribute_set_title  . ': ' .  $attribute->title;
                        endif;
                    endforeach;
                endif;

                return [
                    "id"            => $item->id,
                    "options"       => $item->options,
                    "price"         => $item->price,
                    "product"       => new ProductResource($item->product),
                    "product_id"    => $item->product_id,
                    "product_name"  => $item->product_name,
                    "qty"           => $item->qty,
                    "attribute"     => $att,

                ];
            }),
            "status"                => $this->status,
            "sub_total"             => $this->sub_total,
            "address"               => $this->address,
            "amount"                => $this->amount,
            "discount_amount"       => $this->discount_amount,
            "payment_method"        => $this->payment->payment_channel->label(),
            "shipping_method_name"  => $this->shipping_method_name,
            "payment_status"        => $this->payment->status->toHtml()
        ];
    }
}
