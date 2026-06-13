<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CalloutSystemSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'platform_admin'))->first();

        $markdownPath = database_path('seeders/data/callout-system.md');

        if (!File::exists($markdownPath)) {
            $this->command->error("Markdown file not found at: {$markdownPath}");

            return;
        }

        Page::updateOrCreate(
            ['slug' => 'callout-system'],
            [
                'title' => 'How the callout system works',
                'content' => File::get($markdownPath),
                'user_id' => $admin?->id,
            ]
        );
    }
}
