<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;

#[ScopedBy([IsActiveScope::class])]
class User extends Authenticatable implements \OwenIt\Auditing\Contracts\Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'photo',
        'bio',
        'phone',
        'bca_number',
        'tos_agreed_at',
        'privacy_policy_agreed_at',
        'email_trophies',
        'email_tagged',
        'email_platform_news',
        'visibility_addable',
        'onboarding_completed_at',
        'pip_agreement_signed_at',
    ];

    /**
     * The attributes that are not mass assignable.
     * Protects against privilege escalation attacks.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'is_active',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Auto-generate a random 7-character string ID for new users.
     * Using a non-sequential primary key prevents IDOR / enumeration attacks.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->id)) {
                $user->id = Str::random(7);
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
        'phone_verification_code',
    ];

    protected $appends = [
        'is_admin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_verification_sent_at' => 'datetime',
            'is_active' => 'boolean',
            'tos_agreed_at' => 'datetime',
            'privacy_policy_agreed_at' => 'datetime',
            'email_trophies' => 'boolean',
            'email_tagged' => 'boolean',
            'email_platform_news' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'pip_agreement_signed_at' => 'datetime',
        ];
    }

    /** Whether this user has a confirmed (SMS-verified) phone number. */
    public function phoneVerified(): bool
    {
        return !empty($this->phone) && $this->phone_verified_at !== null;
    }

    /**
     * Whether the user is allowed to use the Pip AI assistant.
     * Platform admins always have access; other users must be explicitly opted in
     * via the `pip_access` role by an admin.
     */
    public function canUsePip(): bool
    {
        return $this->hasRole(['platform_admin', 'pip_access']);
    }

    /**
     * Whether the user is allowed to create and manage callouts.
     * Platform admins and duty officers always have access; other users must be
     * explicitly opted in via the `callout_access` role by an admin.
     */
    public function canUseCallout(): bool
    {
        return $this->hasRole(['platform_admin', 'duty_officer', 'callout_access']);
    }

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class);
    }

    /**
     * The clubs that the user belongs to.
     */
    public function clubs(): BelongsToMany
    {
        // Define the relationship via the pivot table 'club_user'
        // Include the 'is_admin' pivot data
        return $this->belongsToMany(Club::class, 'club_user')
            ->withPivot('is_admin') // Specify pivot columns to retrieve
            ->withPivot('status')
            ->withTimestamps(); // Include pivot timestamps if using them
    }

    public function medals(): BelongsToMany
    {
        return $this->belongsToMany(Medal::class)->withTimestamps()->withPivot('awarded_at');
    }

    /**
     * Get the collections owned by the user.
     */
    public function collections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function activeCallout(): ?\Illuminate\Database\Eloquent\Relations\HasOne
    {
        // This returns the callout where the user is the CREATOR
        // For callouts where user is a participant, we need a different approach
        return $this->hasOne(Callout::class)->whereIn('status', ['active', 'triggered']);
    }

    public function currentOnCallShift(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OnCallShift::class)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now());
    }

    /**
     * Get the user's active callout (either as creator or participant).
     */
    public function getActiveCalloutAttribute()
    {
        // Use eager-loaded relation if available (avoids N+1 in collection responses)
        if ($this->relationLoaded('activeCallout')) {
            return $this->getRelation('activeCallout');
        }

        // First check if user created an active callout
        $callout = $this->callouts()->whereIn('status', ['active', 'triggered'])->first();

        if ($callout) {
            return $callout->load(['cave', 'participants', 'incident']);
        }

        // Otherwise check if user is a participant in any active callout
        $participantCallout = Callout::whereIn('status', ['active', 'triggered'])
            ->whereHas('participants', function ($query) {
                $query->where('user_id', $this->id);
            })
            ->with(['cave', 'participants', 'incident'])
            ->first();

        return $participantCallout;
    }

    public function callouts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Callout::class);
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string|array $role): bool
    {
        if ($this->relationLoaded('roles')) {
            $slugs = $this->roles->pluck('slug');

            return is_array($role)
                ? $slugs->intersect($role)->isNotEmpty()
                : $slugs->contains($role);
        }

        if (is_array($role)) {
            return $this->roles()->whereIn('slug', $role)->exists();
        }

        return $this->roles()->where('slug', $role)->exists();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(string $role): void
    {
        $roleModel = Role::where('slug', $role)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string $role): void
    {
        $roleModel = Role::where('slug', $role)->firstOrFail();
        $this->roles()->detach($roleModel->id);
    }

    /**
     * Returns true if the user has any admin role.
     * Used to gate access to the admin panel. `pip_access` and `callout_access`
     * are feature flags, not admin roles, so they must not count here.
     */
    public function getIsAdminAttribute(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->whereNotIn('slug', ['pip_access', 'callout_access'])->isNotEmpty();
        }

        return $this->roles()->whereNotIn('slug', ['pip_access', 'callout_access'])->exists();
    }

    /**
     * Check if the user has any approved club membership.
     */
    protected ?bool $hasApprovedClubCache = null;

    public function hasApprovedClub(): bool
    {
        if ($this->hasApprovedClubCache !== null) {
            return $this->hasApprovedClubCache;
        }

        if ($this->relationLoaded('clubs')) {
            return $this->hasApprovedClubCache = $this->clubs->contains(function ($club) {
                return $club->pivot->status === 'approved';
            });
        }

        return $this->hasApprovedClubCache = $this->clubs()->wherePivot('status', 'approved')->exists();
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function administeredPermits(): BelongsToMany
    {
        return $this->belongsToMany(Permit::class, 'permit_user')->withTimestamps();
    }
}
