<?php

namespace App\Providers;

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
        Club::class => ClubPolicy::class, // Register the ClubPolicy
    ];

}
