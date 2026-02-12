<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'tos_agreed_at',
        'privacy_policy_agreed_at',
        'email_trophies',
        'email_tagged',
        'email_platform_news',
        'visibility_addable',
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
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
            'is_active' => 'boolean',
            'tos_agreed_at' => 'datetime',
            'privacy_policy_agreed_at' => 'datetime',
            'email_trophies' => 'boolean',
            'email_tagged' => 'boolean',
            'email_platform_news' => 'boolean',
        ];
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

    /**
     * Get the user's active callout (either as creator or participant).
     */
    public function getActiveCalloutAttribute()
    {
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
     * Used to gate access to the admin panel.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->roles()->exists();
    }

    /**
     * Check if the user has any approved club membership.
     */
    public function hasApprovedClub(): bool
    {
        return $this->clubs()->wherePivot('status', 'approved')->exists();
    }
}
