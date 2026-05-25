<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'email_trophies' => true,
            'email_tagged' => true,
            'email_platform_news' => true,
            'visibility_addable' => 'public',
        ];
    }

    /**
     * State: user with platform_admin role.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('platform_admin');
        });
    }

    /**
     * State: user with duty_officer role.
     */
    public function dutyOfficer(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('duty_officer');
        });
    }

    /**
     * State: user with data_admin role.
     */
    public function dataAdmin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('data_admin');
        });
    }

    /**
     * State: user with access_officer role.
     */
    public function accessOfficer(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('access_officer');
        });
    }

    /**
     * State: user explicitly granted pip_access (Pip AI assistant).
     */
    public function pipAccess(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('pip_access');
        });
    }

    /**
     * State: user has accepted the Pip agreement.
     */
    public function pipAgreed(): static
    {
        return $this->state(fn () => ['pip_agreement_signed_at' => now()]);
    }

    /**
     * State: user with an approved club membership.
     */
    public function withApprovedClub(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $club = \App\Models\Club::firstOrCreate(
                ['slug' => 'test-club'],
                ['name' => 'Test Club', 'is_active' => true]
            );
            $user->clubs()->syncWithoutDetaching([$club->id => ['status' => 'approved']]);
        });
    }
}
