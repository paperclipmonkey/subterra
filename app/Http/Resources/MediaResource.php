<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            "id"=> $this->id,
            "filename"=> $this->filename,
            "url"=> Storage::disk('media')->url($this->filename),
            "taken_at"=> $this->taken_at,
            "photographer"=> $this->photographer,
            "copyright"=> $this->copyright,
        ];
    }
}
