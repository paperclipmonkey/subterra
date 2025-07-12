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
    use HasFactory, Notifiable, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'photo',
        'is_active',
        'is_approved',
        'bio',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
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
            'approved' => 'boolean',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
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
}
