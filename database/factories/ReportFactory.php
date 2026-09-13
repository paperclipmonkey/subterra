<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => Trip::class,
            'reportable_id' => fn () => (string) Trip::factory()->create()->id,
            'category' => 'harassment',
            'details' => fake()->sentence(),
            'status' => 'open',
        ];
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['category' => 'child_safety']);
    }

    public function resolved(string $status = 'actioned'): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'handled_by' => User::factory(),
            'handled_at' => now(),
            'resolution_note' => 'Handled.',
        ]);
    }

    /** An objection raised by the subject from the notice email, so no reporter. */
    public function objection(): static
    {
        return $this->state(fn () => [
            'reporter_id' => null,
            'category' => 'data_objection',
            'reportable_type' => User::class,
            'reportable_id' => fn () => (string) User::factory()->create()->id,
        ]);
    }
}
