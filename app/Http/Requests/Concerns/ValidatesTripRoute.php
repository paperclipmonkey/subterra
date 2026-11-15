<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\ViewerRoute;

/**
 * A trip's route through its cave's 3D survey, with the times the party
 * reached each stop where they noted them (see ViewerRoute).
 */
trait ValidatesTripRoute
{
    protected function prepareForValidation(): void
    {
        if ($this->has('route')) {
            $this->merge(['route' => ViewerRoute::decode($this->input('route'))]);
        }
    }

    /** @return array<string, mixed> */
    protected function routeRules(): array
    {
        return ViewerRoute::rules('route', times: true);
    }

    /**
     * @param  array<string, mixed>|null  $route
     * @return array<string, mixed>|null
     */
    public static function cleanRoute(?array $route): ?array
    {
        return ViewerRoute::clean($route, times: true);
    }
}
