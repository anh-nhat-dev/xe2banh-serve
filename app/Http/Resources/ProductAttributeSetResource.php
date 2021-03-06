<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\{ProductAttributeResource};

class ProductAttributeSetResource extends JsonResource
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

        return [
            "id" => $this->id,
            "title" => $this->title,
            "attributes" => ProductAttributeResource::collection($this->attributes)
        ];
    }
}
