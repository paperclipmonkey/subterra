<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AboutPageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('slug', 'platform_admin');
        })->first();

        // Fallback to first user if no platform_admin exists (e.g. in dev)
        $adminId = $admin ? $admin->id : (User::first() ? User::first()->id : null);

        $markdownPath = database_path('seeders/data/about.md');

        if (!File::exists($markdownPath)) {
            $this->command->error("Markdown file not found at: {$markdownPath}");

            return;
        }

        $content = File::get($markdownPath);

        Page::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About Subterra',
                'content' => $content,
                'user_id' => $adminId,
            ]
        );

        $this->command->info('About page seeded successfully.');
    }
}
