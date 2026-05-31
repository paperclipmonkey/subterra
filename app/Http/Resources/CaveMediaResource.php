<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaveMediaResource extends JsonResource
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
            'type' => $this->type,
            'filename' => $this->filename,
            'url' => $this->filename ? (str_starts_with($this->filename, 'http') ? $this->filename : \Illuminate\Support\Facades\Storage::disk('media')->url($this->filename)) : null,
            'title' => $this->title,
            'photographer' => $this->photographer,
            'copyright' => $this->copyright,
        ];
    }
}
