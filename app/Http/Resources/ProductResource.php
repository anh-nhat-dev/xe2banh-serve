<?php

namespace App\Http\Resources;

use App\Http\Resources\{VariationResource, CategoryResource};
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
            "id"                        => $this->id,
            "name"                  => $this->name,
            "slug"                  => $this->slug,
            "sku"                   => $this->sku,
            "sale_percentage"       => get_sale_percentage($this->price, $this->front_sale_price),
            "price"                 => $this->price,
            "sale_price"            => $this->sale_price,
            "image"                 => RvMedia::getImageUrl($this->original_product->image, null, false),
            "front_sale_price"      => $this->front_sale_price,
            "promotions"            => $this->promotions,
            "variations"            => VariationResource::collection($this->variations),
            "images" => collect($this->images)->map(function ($link) {
                return RvMedia::getImageUrl($link, null, false);
            }),
        ];

        $advanced_response = [
            // "images" => collect($this->images)->map(function ($link) {
            //     return RvMedia::getImageUrl($link, null, false);
            // }),
            "description" => $this->description,
            "content"   => $this->content,
            "productAttributeSets" => $this->productAttributeSets,
            "brand" => $this->brand,
            "average_star" => get_average_star_of_product($this->id),
            "total_reviewed" => get_count_reviewed_of_product($this->id),
            "categories" => CategoryResource::collection($this->categories),
            "out_of_stock" => $this->isOutOfStock()
        ];

        return empty($is_single) ? $normal_response : array_merge($normal_response, $advanced_response);
    }
}
