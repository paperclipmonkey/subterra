<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaveSystemFile>
 */
class CaveSystemFileFactory extends Factory
{
    protected $model = CaveSystemFile::class;

    public function definition(): array
    {
        return [
            'cave_system_id' => CaveSystem::factory(),
            'filename' => Str::random(40).'.pdf',
            'original_filename' => 'survey.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12345,
            'kind' => 'document',
            'visibility' => 'public',
            'sort_order' => 0,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => 'private']);
    }

    public function photo(): static
    {
        return $this->state(fn () => [
            'kind' => 'photo',
            'filename' => Str::random(40).'.jpg',
            'original_filename' => 'historic.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
