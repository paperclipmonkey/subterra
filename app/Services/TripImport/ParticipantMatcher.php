<?php

declare(strict_types=1);

namespace App\Services\TripImport;

use App\Models\User;
use App\Support\AddableUsers;
use Illuminate\Support\Facades\DB;

/**
 * Matches companion names from a logbook to Subterra users the importer is
 * allowed to tag. Only a single exact (case-insensitive) name match is taken
 * automatically; partial matches ("Bob") come back as candidates to confirm,
 * and names with no match at all become guests, noted in the trip description.
 */
class ParticipantMatcher
{
    public const MATCHED = 'matched';
    public const AMBIGUOUS = 'ambiguous';
    public const GUEST = 'guest';

    /** Words people write in a companions column that mean "nobody else". */
    private const SOLO_WORDS = ['me', 'myself', 'i', 'self', 'solo', 'alone', 'nobody', 'none', 'n/a', 'na', '-'];

    /** @var array<string, array<string, mixed>|null> */
    private array $memo = [];

    public function __construct(private readonly User $importer)
    {
    }

    /**
     * @return array{name: string, user_id: string|null, status: string, candidates?: array<int, array<string, mixed>>}|null
     *                                                                                                                       null when the name refers to the importer or to nobody
     */
    public function match(string $name): ?array
    {
        $name = trim($name);
        $key = mb_strtolower($name);

        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $this->doMatch($name);
        }

        return $this->memo[$key];
    }

    /**
     * @param  string[]  $names
     * @return array<int, array<string, mixed>>
     */
    public function matchAll(array $names): array
    {
        $entries = [];
        $seen = [];
        foreach ($names as $name) {
            $entry = $this->match((string) $name);
            if ($entry === null) {
                continue;
            }
            $dedupe = $entry['user_id'] ?? 'name:'.mb_strtolower($entry['name']);
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $entries[] = $entry;
        }

        return $entries;
    }

    /** Whether the importer may tag this user on a trip. */
    public function canTag(string $userId): bool
    {
        return AddableUsers::query($this->importer)->where('users.id', $userId)->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function doMatch(string $name): ?array
    {
        $normalised = mb_strtolower($name);
        if ($normalised === '' || in_array($normalised, self::SOLO_WORDS, true)) {
            return null;
        }
        if ($normalised === mb_strtolower(trim((string) $this->importer->name))) {
            return null;
        }

        $exact = AddableUsers::query($this->importer)
            ->where(DB::raw('LOWER(users.name)'), $normalised)
            ->limit(6)
            ->get(['users.id', 'users.name']);

        if ($exact->count() === 1) {
            return ['name' => $name, 'user_id' => $exact->first()->id, 'status' => self::MATCHED];
        }

        $candidates = $exact;
        if ($candidates->isEmpty() && mb_strlen($normalised) >= 3) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $normalised).'%';
            $candidates = AddableUsers::query($this->importer)
                ->where(DB::raw('LOWER(users.name)'), 'like', $like)
                ->orderBy('users.name')
                ->limit(6)
                ->get(['users.id', 'users.name']);
        }

        if ($candidates->isEmpty()) {
            return ['name' => $name, 'user_id' => null, 'status' => self::GUEST];
        }

        $clubs = AddableUsers::clubNames($candidates->pluck('id')->all());

        return [
            'name' => $name,
            'user_id' => null,
            'status' => self::AMBIGUOUS,
            'candidates' => $candidates->map(fn ($u) => [
                'user_id' => $u->id,
                'name' => $u->name,
                'clubs' => $clubs[$u->id] ?? [],
            ])->values()->all(),
        ];
    }
}
