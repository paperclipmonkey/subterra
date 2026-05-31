<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed test users with various roles and configurations.
     */
    public function run(): void
    {
        $activeClub = Club::where('name', 'Active Club')->first();
        $disabledClub = Club::where('name', 'Disabled Club')->first();

        // 1. Admin User - Platform administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@subterra.test'],
            [
                'name' => 'Admin User',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'Platform administrator with full access.',
                'phone' => '+44 7700 900001',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => true,
                'visibility_addable' => 'public',
            ]
        );
        $admin->assignRole('platform_admin');

        if ($activeClub) {
            $admin->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => true, 'status' => 'approved'],
            ]);
        }

        // 2. Club Admin - Regular user who is a club administrator
        $clubAdmin = User::firstOrCreate(
            ['email' => 'clubadmin@subterra.test'],
            [
                'name' => 'Club Admin',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'Administrator of the Active Club.',
                'phone' => '+44 7700 900002',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => false,
                'visibility_addable' => 'public',
            ]
        );

        if ($activeClub) {
            $clubAdmin->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => true, 'status' => 'approved'],
            ]);
        }

        // 3. Regular Member - Approved club member
        $member = User::firstOrCreate(
            ['email' => 'member@subterra.test'],
            [
                'name' => 'Regular Member',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'Active caver and club member.',
                'phone' => '+44 7700 900003',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => true,
                'visibility_addable' => 'public',
            ]
        );

        if ($activeClub) {
            $member->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => false, 'status' => 'approved'],
            ]);
        }

        // 4. Pending Member - Waiting for club approval
        $pending = User::firstOrCreate(
            ['email' => 'pending@subterra.test'],
            [
                'name' => 'Pending Member',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'New user waiting for club approval.',
                'phone' => '+44 7700 900004',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => true,
                'visibility_addable' => 'public',
            ]
        );

        if ($activeClub) {
            $pending->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => false, 'status' => 'pending'],
            ]);
        }

        // 5. Private User - Member with private visibility
        $privateUser = User::firstOrCreate(
            ['email' => 'private@subterra.test'],
            [
                'name' => 'Private User',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'Prefers to keep profile private.',
                'phone' => '+44 7700 900005',
                'email_trophies' => false,
                'email_tagged' => false,
                'email_platform_news' => false,
                'visibility_addable' => 'private',
            ]
        );

        if ($activeClub) {
            $privateUser->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => false, 'status' => 'approved'],
            ]);
        }

        // 6. Multi-Club Member - Member of both clubs
        $multiClub = User::firstOrCreate(
            ['email' => 'multiclub@subterra.test'],
            [
                'name' => 'Multi Club Member',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'Member of multiple clubs.',
                'phone' => '+44 7700 900006',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => true,
                'visibility_addable' => 'clubs',
            ]
        );

        if ($activeClub) {
            $multiClub->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => false, 'status' => 'approved'],
            ]);
        }

        if ($disabledClub) {
            $multiClub->clubs()->syncWithoutDetaching([
                $disabledClub->id => ['is_admin' => false, 'status' => 'approved'],
            ]);
        }

        // 7. Inactive User - Deactivated account
        $inactive = User::firstOrCreate(
            ['email' => 'inactive@subterra.test'],
            [
                'name' => 'Inactive User',
                'is_active' => false,
                'is_active' => false,
                'bio' => 'This account has been deactivated.',
                'phone' => '+44 7700 900007',
                'email_trophies' => false,
                'email_tagged' => false,
                'email_platform_news' => false,
                'visibility_addable' => 'public',
            ]
        );

        if ($activeClub) {
            $inactive->clubs()->syncWithoutDetaching([
                $activeClub->id => ['is_admin' => false, 'status' => 'approved'],
            ]);
        }

        // 8. No Club Member - Approved but not in any club
        User::firstOrCreate(
            ['email' => 'noclub@subterra.test'],
            [
                'name' => 'No Club Member',
                'is_active' => true,
                'is_active' => true,
                'bio' => 'User without club membership.',
                'phone' => '+44 7700 900008',
                'email_trophies' => true,
                'email_tagged' => true,
                'email_platform_news' => true,
                'visibility_addable' => 'public',
            ]
        );

        $this->command->info('Created 8 test users with various roles and configurations.');
    }
}
