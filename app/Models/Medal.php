<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Medal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image_path',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('awarded_at');
    }

    public function imageUrl(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        return Storage::disk('medals')->url($this->image_path);
    }

    /**
     * URL of a raster version of the badge, for contexts that can't render SVG
     * (email clients, overwhelmingly). Returns null rather than a URL to a file
     * that isn't there, so callers can omit the image instead of emitting a
     * broken one — which is how the archivist medal shipped without a picture.
     */
    public function rasterImageUrl(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        $rasterPath = preg_replace('/\.svg$/i', '.png', $this->image_path);

        $disk = Storage::disk('medals');

        if ($disk->exists($rasterPath)) {
            return $disk->url($rasterPath);
        }

        // No raster available — only offer the original if it is already one.
        return str_ends_with(strtolower($this->image_path), '.svg')
            ? null
            : $disk->url($this->image_path);
    }
}
