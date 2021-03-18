<?php

namespace App\Http\Resources;

use App\Http\Resources\{SimpleSliderItemResource};
use Illuminate\Http\Resources\Json\JsonResource;


class SimpleSliderResource extends JsonResource
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
            "items" => SimpleSliderItemResource::collection($this->sliderItems)
        ];
    }
}
