<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use RvMedia;

class CategoryResource extends JsonResource
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
            "id"   => $this->id,
            "image"  => RvMedia::getImageUrl($this->image, null, false),
            "name" => $this->name,
            "description"  =>  $this->description,
            "slug" => $this->slug,
            "children" => CategoryResource::collection($this->children)
        ];
    }
}
