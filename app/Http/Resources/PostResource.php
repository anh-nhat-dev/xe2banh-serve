<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use RvMedia;

class PostResource extends JsonResource
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
            "image"  => RvMedia::getImageUrl($this->image, null, false),
            "created_at" => $this->created_at
        ];

        $advanced_response = [
            "content" => $this->content,
            "categories" => collect($this->categories)->map(function ($cate) {
                return [
                    "id" => $cate->id,
                    "name" => $cate->name,
                    "slug" => $cate->slug,
                ];
            }),
            "views" => $this->views,

        ];

        return empty($is_single) ? $normal_response : array_merge($normal_response, $advanced_response);
    }
}
