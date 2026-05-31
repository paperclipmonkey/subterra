<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Club;
use App\Policies\ClubPolicy;
use Illuminate\Support\ServiceProvider;

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
