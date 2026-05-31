<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaveSummaryResource extends JsonResource
{
    protected static ?string $mediaUrlBase = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate previously_done tag
        $hasDone = $this->has_visited_system ?? false;
        $previoslyDoneTag = $hasDone ? CaveResource::getCachedTag('Previously Done') : CaveResource::getCachedTag('Not Done Yet');

        // Build tags array manually to avoid TagResource/Collection overhead per item
        $formattedTags = [];

        // 1. Existing tags
        if ($this->relationLoaded('tags')) {
            foreach ($this->tags as $tag) {
                if ($tag instanceof Tag) {
                    $formattedTags[] = $this->formatTag($tag);
                }
            }
        }

        // 2. Previously Done tag
        if ($previoslyDoneTag instanceof Tag) {
            $formattedTags[] = $this->formatTag($previoslyDoneTag);
        }

        // 3. System length tags
        $systemLengthTags = [];
        if ($this->system) {
            $length = $this->system->length;
            if ($length >= 5000) {
                $this->addTagByLabel($systemLengthTags, '> 5km');
            }
            if ($length >= 1000) {
                $this->addTagByLabel($systemLengthTags, '> 1km');
            }
            if ($length >= 500) {
                $this->addTagByLabel($systemLengthTags, '> 500m');
            }
            if ($length >= 250) {
                $this->addTagByLabel($systemLengthTags, '> 250m');
            } elseif ($length > 0) {
                $this->addTagByLabel($systemLengthTags, '< 250m');
            }
        }

        foreach ($systemLengthTags as $tag) {
            $formattedTags[] = $tag;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'hero_image' => $this->formatMedia($this->heroImage),
            'hero_video' => $this->formatMedia($this->heroVideo),
            'entrance_image' => $this->formatMedia($this->entranceImage),
            'tags' => $formattedTags,
            'location_name' => $this->location_name,
            'location_country' => $this->location_country,
            'location_lat' => $request->user()?->hasApprovedClub() ? $this->location_lat : null,
            'location_lng' => $request->user()?->hasApprovedClub() ? $this->location_lng : null,
            'system' => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'catchment_id' => $this->system->catchment_id,
                'length' => $this->system->length,
                'vertical_range' => $this->system->vertical_range,
                'tags' => $this->getSystemTags($systemLengthTags),
            ],
            'previously_done' => $hasDone,
        ];
    }

    private function addTagByLabel(array &$tags, string $label): void
    {
        $tag = CaveResource::getCachedTag($label);
        if ($tag instanceof Tag) {
            $tags[] = $this->formatTag($tag);
        }
    }

    private function formatTag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'tag' => $tag->tag,
            'category' => $tag->category,
            'slug' => $tag->slug,
            'icon' => $tag->icon,
            'color' => $tag->color,
        ];
    }

    private function getSystemTags(array $lengthTags): array
    {
        $tags = [];
        if ($this->system && $this->system->relationLoaded('tags')) {
            foreach ($this->system->tags as $tag) {
                $tags[] = $this->formatTag($tag);
            }
        }

        return array_merge($tags, $lengthTags);
    }

    private function formatMedia($media): ?array
    {
        if (!$media) {
            return null;
        }

        if (self::$mediaUrlBase === null) {
            self::$mediaUrlBase = rtrim(Storage::disk('media')->url(''), '/');
        }

        return [
            'id' => $media->id,
            'type' => $media->type,
            'filename' => $media->filename,
            'url' => $media->filename
                ? (str_starts_with($media->filename, 'http') ? $media->filename : self::$mediaUrlBase.'/'.$media->filename)
                : null,
            'title' => $media->title,
            'photographer' => $media->photographer,
            'copyright' => $media->copyright,
        ];
    }
}
