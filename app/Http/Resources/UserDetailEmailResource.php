<?php

declare(strict_types=1);

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
            'email' => $this->email,
            'photo' => $this->photo,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'email_trophies' => $this->email_trophies,
            'email_tagged' => $this->email_tagged,
            'email_platform_news' => $this->email_platform_news,
            'visibility_addable' => $this->visibility_addable,
            'is_admin' => $this->is_admin,
            // Eager load approvedClubs if not already done in controller
            'clubs' => $this->clubs->map(function ($club) {
                return [
                    'id' => $club->id,
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
            'roles' => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'stats' => [
                'trips' => $this->trips->count(),
                'caves' => $this->trips->pluck('cave_system_id')->unique()->count(),
                'duration' => $this->trips->sum('duration'),
            ],
            'active_callout' => $this->active_callout, // Uses getActiveCalloutAttribute() which checks both creator and participant
            'on_call' => $this->relationLoaded('currentOnCallShift')
                ? $this->currentOnCallShift !== null
                : OnCallShift::covering(now())->where('user_id', $this->id)->exists(),
            'on_call_until' => $this->relationLoaded('currentOnCallShift')
                ? $this->currentOnCallShift?->end_at
                : OnCallShift::covering(now())->where('user_id', $this->id)->first()?->end_at,
            'open_callouts_count' => once(fn () => Callout::whereIn('status', ['active', 'triggered'])->count()),
            'tos_agreed_at' => $this->tos_agreed_at,
            'privacy_policy_agreed_at' => $this->privacy_policy_agreed_at,
            'pip_agreement_signed_at' => $this->pip_agreement_signed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'onboarding_completed_at' => $this->onboarding_completed_at,
        ];
    }
}
