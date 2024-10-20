<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
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
            'email'=> $this->email,
            'photo' => $this->photo,
            'club' => $this->club,
            'stats'=> [
                'trips' => $this->trips->count(),
                'caves' => $this->trips->pluck('system.id')->unique()->count(),
                'duration' => $this->trips->sum('duration'),
            ],
        ];
    }
}
