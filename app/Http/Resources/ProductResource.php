<?php

namespace App\Http\Resources;

use App\Http\Resources\VariationResource;
use Illuminate\Http\Resources\Json\JsonResource;
use RvMedia;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $is_single = $request->input('is_single');

        $normal_response = [
            "id" => $this->id,
            "name" => $this->name,
            "slug" => $this->slug,
            "sku" => $this->sku,
            "sale_percentage" => get_sale_percentage($this->price, $this->front_sale_price),
            "price" => $this->price,
            "sale_price" => $this->sale_price,
            "image"  => RvMedia::getImageUrl($this->image, null, false),
            "front_sale_price" => $this->front_sale_price,

        ];

        $advanced_response = [
            "images" => collect($this->images)->map(function ($link) {
                return RvMedia::getImageUrl($link, null, false);
            }),
            "productAttributes" => $this->productAttributes,
            "productAttributeSets" => $this->productAttributeSets,
            "variations" => VariationResource::collection($this->variations),
            "brand" => $this->brand,
        ];



        return !isset($is_single) ? $normal_response : array_merge($normal_response, $advanced_response);
    }
}
