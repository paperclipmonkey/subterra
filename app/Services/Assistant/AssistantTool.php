<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\User;

interface AssistantTool
{
    /**
     * The OpenAI-compatible function definition sent to the model.
     */
    public static function definition(): array;

    /**
     * Execute the tool with the arguments supplied by the model.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, User $user): array;
}
