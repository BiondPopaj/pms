<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'description'     => $this->description,
            'base_occupancy'  => $this->base_occupancy,
            'max_occupancy'   => $this->max_occupancy,
            'max_adults'      => $this->max_adults,
            'max_children'    => $this->max_children,
            'bed_type'        => $this->bed_type,
            'size_sqm'        => $this->size_sqm,
            'amenities'       => $this->amenities ?? [],
            'photos'          => $this->photos ?? [],
            'base_rate'       => $this->base_rate,
            'is_active'       => $this->is_active,
            'sort_order'      => $this->sort_order,
            'created_at'      => $this->created_at->toISOString(),
            'rooms_count'     => $this->whenCounted('rooms'),
            'active_rooms_count' => $this->when(
                $this->relationLoaded('rooms'),
                fn () => $this->rooms->where('is_active', true)->count()
            ),
        ];
    }
}
