<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TripMedia */
class MediaResource extends JsonResource
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
            'filename' => $this->filename,
            'title' => $this->title,
            'url' => MediaUrl::url($this->filename),
            'srcset' => MediaUrl::srcset($this->filename),
            'taken_at' => $this->taken_at,
            'photographer' => $this->photographer,
            'copyright' => $this->copyright,
        ];
    }
}
