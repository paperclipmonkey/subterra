<?php

namespace App\Providers;

// ... other use statements ...
use App\Models\Club;
use App\Policies\ClubPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Club::class => ClubPolicy::class, // Register the ClubPolicy
    ];

    // ... rest of the class ...
}
