<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaveSystemFileResource extends JsonResource
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
            'filename' => $this->filename, // The stored filename
            'original_filename' => $this->original_filename, // The original uploaded name
            'details' => $this->details,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => Storage::disk('media')->url("cave_system_files/{$this->cave_system_id}/{$this->filename}"),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}

