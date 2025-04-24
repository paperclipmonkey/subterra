<?php

namespace App\Models;

use App\Models\Scopes\IsActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ScopedBy([IsActiveScope::class])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'photo',
        'club',
        'is_active',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
    ];

    public function trips()
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

    public function medals()
    {
        return $this->belongsToMany(Medal::class)->withTimestamps()->withPivot('awarded_at');
    }
}
