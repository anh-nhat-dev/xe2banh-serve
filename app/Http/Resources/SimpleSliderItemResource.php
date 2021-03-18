<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use RvMedia;

class SimpleSliderItemResource extends JsonResource
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
            "id" => $this->id,
            "image" => RvMedia::getImageUrl($this->image, null, false),
            "link" => $this->link,
            "title" => $this->title
        ];
    }
}
