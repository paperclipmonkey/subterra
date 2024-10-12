<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cave_system' => $this->cave_system,
            'cave_entrance' => $this->cave_entrace,
            'cave_exit' => $this->cave_exit,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'participants' => $this->participants
        ];
    }
}
