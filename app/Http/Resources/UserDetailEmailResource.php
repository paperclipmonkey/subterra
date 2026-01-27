<?php

namespace App\Http\Resources;

use App\Models\Callout;
use App\Models\OnCallShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserDetailEmailResource extends JsonResource
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
            'bio' => $this->bio,
            'phone' => $this->phone,
            'email_trophies' => $this->email_trophies,
            'email_tagged' => $this->email_tagged,
            'email_platform_news' => $this->email_platform_news,
            'visibility_addable' => $this->visibility_addable,
            // Eager load approvedClubs if not already done in controller
            'clubs' => $this->clubs->map(function ($club) {
                return [
                    'name' => $club->name,
                    'slug' => $club->slug,
                    'is_admin' => $club->pivot->is_admin,
                    'status' => $club->pivot->status,
                ];
            }),
            'medals' => $this->medals->map(function ($medal) {
                return [
                    'id' => $medal->id,
                    'name' => $medal->name,
                    'description' => $medal->description,
                    'image_url' => $medal->image_path ? Storage::disk('medals')->url($medal->image_path) : null,
                    'awarded_at' => $medal->pivot->awarded_at ?? null,
                ];
            }),
            'is_admin' => $this->is_admin,
            'is_approved' => $this->is_approved,
            'stats'=> [
                'trips' => $this->trips->count(),
                'caves' => $this->trips->pluck('system.id')->unique()->count(),
                'duration' => $this->trips->sum('duration'),
            ],
            'active_callout' => $this->activeCallout ? $this->activeCallout->load(['cave', 'participants', 'incident']) : null,
            'on_call' => OnCallShift::covering(now())->where('user_id', $this->id)->exists(),
            'on_call_until' => OnCallShift::covering(now())->where('user_id', $this->id)->first()?->end_at,
            'open_callouts_count' => Callout::whereIn('status', ['active', 'triggered'])->count(),
            'tos_agreed_at' => $this->tos_agreed_at,
            'privacy_policy_agreed_at' => $this->privacy_policy_agreed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
