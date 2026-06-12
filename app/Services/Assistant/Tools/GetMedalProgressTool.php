<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\MedalProgressService;

class GetMedalProgressTool implements AssistantTool
{
    public function __construct(private MedalProgressService $medalProgress)
    {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_medal_progress',
                'description' => "Get the FULL medal catalogue with the current user's progress: every medal's name, "
                    .'earning criteria, whether the user has earned it (with date), and progress toward unearned ones '
                    .'(e.g. 3/5 caves for Explorer). Call this when the user asks about medals, trophies, achievements, '
                    .'what they could earn next, or how to earn a specific medal. Prefer this over get_user_experience '
                    .'for medal questions — that tool only lists medals already earned, not the ones still available.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $medals = $this->medalProgress->progressForUser($user);

        [$earned, $unearned] = $medals->partition(fn ($medal) => $medal['earned']);

        // image_url is for the UI medal card — AssistantService strips it from
        // the LLM context before the result reaches the model.
        $earned = $earned->map(fn ($medal) => [
            'name' => $medal['name'],
            'description' => $medal['description'],
            'awarded_at' => $medal['awarded_at'] ? date('Y-m-d', strtotime((string) $medal['awarded_at'])) : null,
            'image_url' => $medal['image_url'],
        ])->values();

        // Nearest-to-completion first so "what's next" answers write themselves
        $unearned = $unearned
            ->sortByDesc(fn ($medal) => $medal['progress']
                ? $medal['progress']['current'] / $medal['progress']['target']
                : 0)
            ->map(fn ($medal) => [
                'name' => $medal['name'],
                'description' => $medal['description'],
                'progress' => $medal['progress'],
                'image_url' => $medal['image_url'],
            ])->values();

        return [
            'summary' => "{$earned->count()} of {$medals->count()} medals earned.",
            'earned' => $earned->all(),
            'unearned' => $unearned->all(),
            'note' => 'unearned is sorted nearest-to-completion first. progress is {current, target} '
                .'(e.g. 3/5 caves visited); a null progress means the medal is awarded manually. When '
                .'reporting, quote summary verbatim, lead with the closest unearned medals, and link to '
                .'the full medal page as [your medals](/medals).',
        ];
    }
}
