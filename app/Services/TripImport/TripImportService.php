<?php

declare(strict_types=1);

namespace App\Services\TripImport;

use App\Events\UserContributed;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\TripImport;
use App\Models\TripImportRow;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stages, resolves and commits a user's trip import.
 *
 * Everything that can be decided mechanically is decided here: parsing,
 * matching caves and people, spotting duplicates, closed caves and bad dates.
 * Pip's job is reduced to asking the user about whatever is left and calling
 * the resolve methods with their answers, and trips are only ever created by
 * {@see commit()} from rows that pass every check.
 */
class TripImportService
{
    private const VISIBILITIES = ['public', 'club', 'private'];

    /** A trip longer than this is probably a mis-parsed duration (or a camp). */
    private const LONG_TRIP_MINUTES = 48 * 60;

    public function __construct(private readonly LogbookParser $parser)
    {
    }

    public function openImportFor(User $user): ?TripImport
    {
        return TripImport::where('user_id', $user->id)
            ->where('status', TripImport::STATUS_OPEN)
            ->latest('id')
            ->first();
    }

    public function openOrCreate(User $user, ?string $filename = null): TripImport
    {
        $import = $this->openImportFor($user);
        if ($import) {
            if ($filename !== null && $import->filename === null) {
                $import->update(['filename' => $filename]);
            }

            return $import;
        }

        return TripImport::create([
            'user_id' => $user->id,
            'status' => TripImport::STATUS_OPEN,
            'filename' => $filename,
        ]);
    }

    /**
     * Parse an uploaded logbook and add its trips to the user's open import.
     *
     * @return array{import: TripImport, added: int, rejected: int, parse: array<string, mixed>}
     *
     * @throws InvalidLogbookException
     */
    public function stageFile(User $user, string $content, ?string $filename = null): array
    {
        $parsed = $this->parser->parse($content);

        if ($parsed['rows'] === []) {
            throw new InvalidLogbookException('I found the column headers but no trips underneath them.');
        }

        $import = $this->openOrCreate($user, $filename);
        $result = $this->addRows($import, $user, $parsed['rows'], 'file');

        unset($parsed['rows']);

        return ['import' => $import, 'added' => count($result['row_numbers']), 'rejected' => $result['rejected'], 'parse' => $parsed];
    }

    /**
     * Stage trips, matching caves and people as they go in.
     *
     * @param  array<int, array<string, mixed>>  $rows  Shape as produced by {@see LogbookParser::parse()}
     * @return array{row_numbers: int[], rejected: int}
     */
    public function addRows(TripImport $import, User $user, array $rows, string $source): array
    {
        $maxRows = (int) config('assistant.import.max_rows', 1000);
        $existing = $import->rows()->count();
        $room = max(0, $maxRows - $existing);
        $accepted = array_slice($rows, 0, $room);

        $nextNumber = ((int) $import->rows()->max('row_number')) + 1;
        $caves = new CaveMatcher();
        $people = new ParticipantMatcher($user);
        $added = [];

        foreach ($accepted as $data) {
            $match = $caves->match($data['cave_name'] ?? null, $data['entrance_name'] ?? null, $data['exit_name'] ?? null);

            $row = new TripImportRow([
                'trip_import_id' => $import->id,
                'row_number' => $nextNumber++,
                'source_line' => $data['source_line'] ?? null,
                'source' => $source,
                'cave_name_raw' => self::nullIfBlank($data['cave_name'] ?? null),
                'entrance_name_raw' => self::nullIfBlank($data['entrance_name'] ?? null),
                'exit_name_raw' => self::nullIfBlank($data['exit_name'] ?? null),
                'cave_system_id' => $match['cave_system_id'],
                'entrance_cave_id' => $match['entrance_cave_id'],
                'exit_cave_id' => $match['exit_cave_id'],
                'cave_candidates' => $match['candidates'] ?: null,
                'date' => $data['date'] ?? null,
                'date_raw' => self::nullIfBlank($data['date_raw'] ?? null),
                'start_time' => $data['start_time'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'name' => self::nullIfBlank($data['name'] ?? null),
                'description' => self::nullIfBlank($data['description'] ?? null),
                'participants' => $people->matchAll((array) ($data['companions'] ?? [])),
            ]);
            $this->evaluate($row, $user);
            $added[] = $row->row_number;
        }

        $import->touch();

        return ['row_numbers' => $added, 'rejected' => count($rows) - count($accepted)];
    }

    /**
     * Recompute a row's issues and status from its current fields, then save.
     * Skipped and imported rows keep their status.
     */
    public function evaluate(TripImportRow $row, User $user): void
    {
        $issues = [];
        $add = function (string $code, string $message, bool $blocking = true) use (&$issues): void {
            $issues[] = ['code' => $code, 'message' => $message, 'blocking' => $blocking];
        };

        $label = $row->cave_name_raw ?? $row->entrance_name_raw;
        $system = $row->cave_system_id ? CaveSystem::find($row->cave_system_id) : null;
        $entrance = $row->entrance_cave_id ? Cave::find($row->entrance_cave_id) : null;

        if (!$system) {
            if ($label === null) {
                $add('cave_missing', 'No cave given.');
            } elseif (empty($row->cave_candidates)) {
                $add('cave_not_found', "'{$label}' isn't in Subterra.");
            } else {
                $add('cave_ambiguous', "'{$label}' needs confirming — it could be ".implode(' or ', array_column($row->cave_candidates, 'name')).'.');
            }
        } elseif (!$entrance) {
            $add('entrance_needed', "Which entrance of {$system->name} was used?");
        } elseif ($row->exit_name_raw !== null && !$row->exit_cave_id) {
            $add('exit_needed', "Which entrance of {$system->name} was the exit ('{$row->exit_name_raw}')?");
        }

        $today = now('Europe/London')->startOfDay();
        if (!$row->date) {
            $row->date_raw !== null
                ? $add('date_unreadable', "The date '{$row->date_raw}' couldn't be read.")
                : $add('date_missing', 'No date given.');
        } elseif ($row->date->copy()->startOfDay()->gt($today)) {
            $add('date_in_future', 'The date '.$row->date->format('j M Y').' is in the future.');
        } elseif ((int) $row->date->format('Y') < 1900) {
            $add('date_implausible', 'The date '.$row->date->format('j M Y').' looks wrong.');
        }

        if ($row->duration_minutes !== null && $row->duration_minutes > self::LONG_TRIP_MINUTES) {
            $add('duration_unusual', 'A duration of '.round($row->duration_minutes / 60, 1).' hours is unusually long — check it.', false);
        }

        foreach ($row->participants ?? [] as $person) {
            if (($person['status'] ?? null) === ParticipantMatcher::AMBIGUOUS) {
                $add('participant_ambiguous', "Which '{$person['name']}' was this?");
            }
        }

        if ($entrance && $this->effectiveVisibility($row) === 'public' && Trip::caveIsClosed($entrance->id)) {
            $add('closed_cave', "{$entrance->name} is a closed cave, so this trip will be saved as private.", false);
        }

        $duplicate = null;
        if ($system && $row->date && !$row->allow_duplicate) {
            $duplicate = $this->findDuplicate($row, $user);
            if ($duplicate !== null) {
                $add('duplicate', $duplicate, false);
            }
        }

        $row->issues = $issues ?: null;

        if (!in_array($row->status, [TripImportRow::STATUS_SKIPPED, TripImportRow::STATUS_IMPORTED], true)) {
            $blocking = collect($issues)->contains('blocking', true);
            $row->status = match (true) {
                $blocking => TripImportRow::STATUS_NEEDS_REVIEW,
                $duplicate !== null => TripImportRow::STATUS_DUPLICATE,
                default => TripImportRow::STATUS_READY,
            };
        }

        $row->save();
    }

    /**
     * Point every pending row with this raw cave name (or the given rows) at a
     * cave system, and optionally a specific entrance/exit.
     *
     * @param  int[]|null  $rowNumbers
     * @return array<string, mixed>
     */
    public function resolveCave(TripImport $import, User $user, ?string $caveName, ?array $rowNumbers, string $systemSlug, ?string $entranceSlug = null, ?string $exitSlug = null): array
    {
        $system = CaveSystem::where('slug', $systemSlug)
            ->whereHas('caves', fn ($q) => $q->where('visibility', 'public'))
            ->first();
        if (!$system) {
            return ['error' => "There is no cave system '{$systemSlug}'. Use find_cave to get the right cave_system_slug."];
        }

        $entrance = null;
        if ($entranceSlug) {
            $entrance = CaveMatcher::publicCaves($system->id)->where('slug', $entranceSlug)->first();
            if (!$entrance) {
                return ['error' => "'{$entranceSlug}' is not an entrance of {$system->name}.", 'system' => CaveMatcher::describeSystem($system)];
            }
        }

        $exit = null;
        if ($exitSlug) {
            $exit = CaveMatcher::publicCaves($system->id)->where('slug', $exitSlug)->first();
            if (!$exit) {
                return ['error' => "'{$exitSlug}' is not an entrance of {$system->name}.", 'system' => CaveMatcher::describeSystem($system)];
            }
        }

        $rows = $this->targetRows($import, $caveName, $rowNumbers);
        if ($rows->isEmpty()) {
            return ['error' => 'No rows waiting for review match that cave name or those row numbers.'];
        }

        $stillNeedEntrance = [];
        foreach ($rows as $row) {
            $row->cave_system_id = $system->id;
            $row->cave_candidates = null;
            $row->entrance_cave_id = ($entrance ?? CaveMatcher::defaultEntrance($system, $row->entrance_name_raw))?->id;
            $row->exit_cave_id = $exit->id
                ?? ($row->exit_name_raw !== null ? CaveMatcher::defaultEntrance($system, $row->exit_name_raw)?->id : null);
            $this->evaluate($row, $user);
            if (!$row->entrance_cave_id) {
                $stillNeedEntrance[] = $row->row_number;
            }
        }
        $import->touch();

        return array_filter([
            'success' => true,
            'rows_updated' => $rows->count(),
            'cave_system' => $system->name,
            'entrance' => $entrance?->name,
            'rows_needing_entrance' => $stillNeedEntrance ?: null,
            'entrances' => $stillNeedEntrance ? CaveMatcher::describeSystem($system)['entrances'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Settle who a companion name refers to across every pending row:
     * tag a Subterra user, record them as a guest, or drop them.
     *
     * @param  int[]|null  $rowNumbers
     * @return array<string, mixed>
     */
    public function resolvePerson(TripImport $import, User $user, string $name, string $action, ?string $userId = null, ?array $rowNumbers = null): array
    {
        if (!in_array($action, ['tag', 'guest', 'remove'], true)) {
            return ['error' => "Unknown action '{$action}'. Use tag, guest or remove."];
        }

        $matcher = new ParticipantMatcher($user);
        $taggedName = null;
        if ($action === 'tag') {
            if ($userId === null || $userId === '') {
                return ['error' => 'A user_id is required to tag someone. Use search_users to find it.'];
            }
            if ($userId === $user->id) {
                $action = 'remove'; // the importer is always on their own trips
            } elseif (!$matcher->canTag($userId)) {
                return ['error' => 'That person can only be tagged by members of their own club, or does not exist. Ask the user whether to record them as a guest instead.'];
            } else {
                $taggedName = User::withoutGlobalScopes()->whereKey($userId)->value('name');
            }
        }

        $needle = mb_strtolower(trim($name));
        $updated = 0;
        foreach ($this->pendingRows($import, $rowNumbers) as $row) {
            $changed = false;
            $participants = [];
            foreach ($row->participants ?? [] as $person) {
                if (mb_strtolower(trim((string) $person['name'])) !== $needle) {
                    $participants[] = $person;

                    continue;
                }
                $changed = true;
                if ($action === 'tag') {
                    $participants[] = ['name' => $person['name'], 'user_id' => $userId, 'status' => ParticipantMatcher::MATCHED];
                } elseif ($action === 'guest') {
                    $participants[] = ['name' => $person['name'], 'user_id' => null, 'status' => ParticipantMatcher::GUEST];
                }
            }
            if ($changed) {
                $row->participants = $this->dedupeParticipants($participants);
                $this->evaluate($row, $user);
                ++$updated;
            }
        }

        if ($updated === 0) {
            return ['error' => "No pending rows have a companion called '{$name}'. Use the names exactly as get_import_status shows them."];
        }
        $import->touch();

        return array_filter([
            'success' => true,
            'rows_updated' => $updated,
            'name' => $name,
            'action' => $action,
            'tagged_as' => $taggedName,
        ], fn ($v) => $v !== null);
    }

    /**
     * Edit, skip or un-skip specific rows.
     *
     * @param  int[]  $rowNumbers
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function updateRows(TripImport $import, User $user, array $rowNumbers, array $changes): array
    {
        $rows = $import->rows()
            ->whereIn('row_number', array_map('intval', $rowNumbers))
            ->where('status', '!=', TripImportRow::STATUS_IMPORTED)
            ->orderBy('row_number')
            ->get();
        if ($rows->isEmpty()) {
            return ['error' => 'None of those rows can be changed (they may not exist or are already imported).'];
        }

        $fields = [];
        if (array_key_exists('date', $changes)) {
            $date = LogbookParser::parseDate((string) $changes['date']);
            if ($date === null) {
                return ['error' => "Couldn't read the date '{$changes['date']}'. Use YYYY-MM-DD."];
            }
            $fields['date'] = $date;
        }
        if (array_key_exists('start_time', $changes)) {
            $time = $changes['start_time'] === null || $changes['start_time'] === '' ? null : LogbookParser::parseTime((string) $changes['start_time']);
            if ($changes['start_time'] && $time === null) {
                return ['error' => "Couldn't read the time '{$changes['start_time']}'. Use HH:MM (24 hour)."];
            }
            $fields['start_time'] = $time;
        }
        if (array_key_exists('duration_minutes', $changes)) {
            $minutes = $changes['duration_minutes'] === null ? null : (int) $changes['duration_minutes'];
            if ($minutes !== null && ($minutes < 0 || $minutes > 14 * 24 * 60)) {
                return ['error' => 'duration_minutes must be between 0 and 20160.'];
            }
            $fields['duration_minutes'] = $minutes;
        }
        if (array_key_exists('name', $changes)) {
            $fields['name'] = self::nullIfBlank(mb_substr((string) $changes['name'], 0, 255));
        }
        if (array_key_exists('description', $changes)) {
            $fields['description'] = self::nullIfBlank(mb_substr((string) $changes['description'], 0, 10000));
        }
        if (array_key_exists('visibility', $changes)) {
            if (!in_array($changes['visibility'], self::VISIBILITIES, true)) {
                return ['error' => 'visibility must be public, club or private.'];
            }
            $fields['visibility'] = $changes['visibility'];
        }
        if (array_key_exists('allow_duplicate', $changes)) {
            $fields['allow_duplicate'] = (bool) $changes['allow_duplicate'];
        }

        $matcher = new ParticipantMatcher($user);
        $newPeople = $matcher->matchAll((array) ($changes['add_companions'] ?? []));

        foreach ($rows as $row) {
            $row->fill($fields);
            if ($newPeople) {
                $row->participants = $this->dedupeParticipants(array_merge($row->participants ?? [], $newPeople));
            }
            if (array_key_exists('skip', $changes)) {
                // Un-skipping drops back to needs_review; evaluate() then works
                // out the real status.
                $row->status = $changes['skip'] ? TripImportRow::STATUS_SKIPPED : TripImportRow::STATUS_NEEDS_REVIEW;
            }
            $this->evaluate($row, $user);
        }
        $import->touch();

        return [
            'success' => true,
            'rows' => $rows->map(fn (TripImportRow $r) => $this->rowBrief($r))->values()->all(),
        ];
    }

    public function setDefaultVisibility(TripImport $import, User $user, string $visibility): array
    {
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            return ['error' => 'visibility must be public, club or private.'];
        }
        $import->update(['default_visibility' => $visibility]);
        // Closed-cave warnings depend on the effective visibility
        foreach ($this->pendingRows($import, null) as $row) {
            $this->evaluate($row, $user);
        }

        return ['success' => true, 'default_visibility' => $visibility];
    }

    public function discard(TripImport $import): array
    {
        $pending = $import->rows()->whereIn('status', TripImportRow::PENDING_STATUSES)->count();
        $import->update(['status' => TripImport::STATUS_DISCARDED]);
        $import->rows()->whereIn('status', TripImportRow::PENDING_STATUSES)->update(['status' => TripImportRow::STATUS_SKIPPED]);

        return ['success' => true, 'rows_discarded' => $pending, 'note' => 'Trips already imported are kept.'];
    }

    /**
     * Create trips from every ready row (or just the given ones).
     *
     * Imports are historic, so this deliberately skips the per-trip side
     * effects of creating a trip by hand — the Slack alert, the duty-officer
     * notice and the "you've been tagged" email — which would otherwise fire
     * hundreds of times for one logbook. Medals are still checked, once per
     * person at the end.
     *
     * @param  int[]|null  $rowNumbers
     * @return array<string, mixed>
     */
    public function commit(TripImport $import, User $user, ?array $rowNumbers = null): array
    {
        $limit = (int) config('assistant.import.max_commit_per_call', 250);
        $rows = $import->rows()
            ->where('status', TripImportRow::STATUS_READY)
            ->when($rowNumbers, fn ($q) => $q->whereIn('row_number', array_map('intval', $rowNumbers)))
            ->orderBy('row_number')
            ->limit($limit)
            ->get();

        $matcher = new ParticipantMatcher($user);
        $imported = [];
        $notImported = [];
        $participantIds = [$user->id => true];

        foreach ($rows as $row) {
            // Re-check: a duplicate may have been created since the row was staged.
            $this->evaluate($row, $user);
            if ($row->status !== TripImportRow::STATUS_READY) {
                $notImported[] = ['row' => $row->row_number, 'reason' => collect($row->issues ?? [])->pluck('message')->implode(' ')];

                continue;
            }

            try {
                $trip = DB::transaction(fn () => $this->createTrip($row, $user, $matcher));
            } catch (\Throwable $e) {
                Log::error('TripImportService: failed to create trip', ['row_id' => $row->id, 'error' => $e->getMessage()]);
                $row->status = TripImportRow::STATUS_FAILED;
                $row->issues = [['code' => 'import_failed', 'message' => 'Saving this trip failed — try again.', 'blocking' => false]];
                $row->save();
                $notImported[] = ['row' => $row->row_number, 'reason' => 'Saving failed.'];

                continue;
            }

            $row->status = TripImportRow::STATUS_IMPORTED;
            $row->trip_id = $trip->id;
            $row->save();

            foreach ($trip->participants as $participant) {
                $participantIds[$participant->id] = true;
            }
            $imported[] = [
                'row' => $row->row_number,
                'trip_url' => "/trips/{$trip->short_id}",
                'name' => $trip->name,
                'date' => $row->date?->format('Y-m-d'),
            ];
        }

        if ($imported !== []) {
            foreach (User::withoutGlobalScopes()->whereIn('id', array_keys($participantIds))->get() as $participant) {
                event(new UserContributed($participant));
            }
            Log::info('Pip trip import committed', ['import_id' => $import->id, 'user_id' => $user->id, 'trips' => count($imported)]);
        }

        $counts = $this->counts($import);
        $pending = $counts['ready'] + $counts['needs_review'] + $counts['duplicate'] + $counts['failed'];
        if ($pending === 0) {
            $import->update(['status' => TripImport::STATUS_COMPLETED]);
        } else {
            $import->touch();
        }

        return [
            'success' => true,
            'imported' => count($imported),
            'trips' => array_slice($imported, 0, 5),
            'not_imported' => $notImported ?: null,
            'remaining' => $counts,
            'more_ready' => $counts['ready'] > 0 ? 'Some ready rows were left for the next call because of the per-call limit.' : null,
            'trips_url' => '/trips',
        ];
    }

    /**
     * @return array{total: int, ready: int, needs_review: int, duplicate: int, skipped: int, imported: int, failed: int}
     */
    public function counts(TripImport $import): array
    {
        $byStatus = $import->rows()
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        $counts = ['total' => (int) $byStatus->sum()];
        foreach ([TripImportRow::STATUS_READY, TripImportRow::STATUS_NEEDS_REVIEW, TripImportRow::STATUS_DUPLICATE, TripImportRow::STATUS_SKIPPED, TripImportRow::STATUS_IMPORTED, TripImportRow::STATUS_FAILED] as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * What's left to do, grouped so one answer from the user settles every
     * row it applies to ("Swildons" on 14 rows is one question, not 14).
     *
     * @param  int[]|null  $rowNumbers  Show these rows in full as well
     * @return array<string, mixed>
     */
    public function summary(TripImport $import, ?array $rowNumbers = null): array
    {
        $rows = $import->rows()->with(['system:id,name,slug', 'entrance:id,name,slug'])->orderBy('row_number')->get();
        $pending = $rows->whereIn('status', [TripImportRow::STATUS_NEEDS_REVIEW, TripImportRow::STATUS_DUPLICATE, TripImportRow::STATUS_FAILED]);

        $caveGroups = [];
        $peopleGroups = [];
        $guests = [];
        $otherIssues = [];
        $duplicates = [];

        foreach ($rows->whereIn('status', TripImportRow::PENDING_STATUSES) as $row) {
            foreach ($row->participants ?? [] as $person) {
                $key = mb_strtolower($person['name']);
                if ($person['status'] === ParticipantMatcher::AMBIGUOUS) {
                    $peopleGroups[$key] ??= ['name' => $person['name'], 'rows' => 0, 'candidates' => $person['candidates'] ?? []];
                    ++$peopleGroups[$key]['rows'];
                } elseif ($person['status'] === ParticipantMatcher::GUEST) {
                    $guests[$key] ??= ['name' => $person['name'], 'rows' => 0];
                    ++$guests[$key]['rows'];
                }
            }
        }

        foreach ($pending as $row) {
            $codes = collect($row->issues ?? [])->pluck('code');
            $caveCode = $codes->first(fn ($c) => in_array($c, ['cave_missing', 'cave_not_found', 'cave_ambiguous', 'entrance_needed', 'exit_needed'], true));

            if ($caveCode !== null) {
                $label = $row->cave_name_raw ?? $row->entrance_name_raw ?? '(blank)';
                $key = $caveCode.'|'.CaveMatcher::normalise($label);
                $caveGroups[$key] ??= [
                    'cave_name' => $label,
                    'problem' => $caveCode,
                    'rows' => 0,
                    'row_numbers' => [],
                    'suggestions' => $this->caveSuggestions($row, $caveCode),
                ];
                ++$caveGroups[$key]['rows'];
                if (count($caveGroups[$key]['row_numbers']) < 10) {
                    $caveGroups[$key]['row_numbers'][] = $row->row_number;
                }
            }

            $other = collect($row->issues ?? [])
                ->reject(fn ($i) => in_array($i['code'], ['cave_missing', 'cave_not_found', 'cave_ambiguous', 'entrance_needed', 'exit_needed', 'participant_ambiguous', 'duplicate'], true));
            if ($other->isNotEmpty() && count($otherIssues) < 15) {
                $otherIssues[] = ['row' => $row->row_number, 'trip' => $this->rowLabel($row), 'issues' => $other->pluck('message')->all()];
            }

            if ($codes->contains('duplicate') && count($duplicates) < 10) {
                $duplicates[] = ['row' => $row->row_number, 'trip' => $this->rowLabel($row), 'issue' => collect($row->issues)->firstWhere('code', 'duplicate')['message']];
            }
        }

        // Warnings on rows that are otherwise ready (long duration, closed cave)
        foreach ($rows->where('status', TripImportRow::STATUS_READY) as $row) {
            $warnings = collect($row->issues ?? [])->pluck('message');
            if ($warnings->isNotEmpty() && count($otherIssues) < 15) {
                $otherIssues[] = ['row' => $row->row_number, 'trip' => $this->rowLabel($row), 'issues' => $warnings->all(), 'ready' => true];
            }
        }

        $summary = [
            'import_id' => $import->id,
            'filename' => $import->filename,
            'default_visibility' => $import->default_visibility,
            'counts' => $this->counts($import),
            'caves_to_resolve' => array_slice(array_values($caveGroups), 0, 12),
            'people_to_resolve' => array_slice(array_values($peopleGroups), 0, 12),
            'guests' => array_slice(array_values($guests), 0, 20),
            'other_issues' => $otherIssues,
            'duplicates' => $duplicates,
            'ready_preview' => $rows->where('status', TripImportRow::STATUS_READY)->take(5)
                ->map(fn (TripImportRow $r) => $this->rowBrief($r))->values()->all(),
        ];

        if (count($caveGroups) > 12 || count($peopleGroups) > 12) {
            $summary['more_to_resolve'] = 'Only the first 12 groups are shown. Resolve these, then check again.';
        }

        if ($rowNumbers) {
            $summary['rows'] = $rows->whereIn('row_number', array_map('intval', $rowNumbers))
                ->take(20)->map(fn (TripImportRow $r) => $this->rowBrief($r, true))->values()->all();
        }

        return array_filter($summary, fn ($v) => $v !== [] && $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function rowBrief(TripImportRow $row, bool $full = false): array
    {
        $row->loadMissing(['system:id,name,slug', 'entrance:id,name,slug', 'exit:id,name,slug']);

        $brief = [
            'row' => $row->row_number,
            'status' => $row->status,
            'date' => $row->date?->format('Y-m-d') ?? $row->date_raw,
            'cave' => $row->system->name ?? $row->cave_name_raw,
            'entrance' => $row->entrance->name ?? $row->entrance_name_raw,
            'exit' => $row->exit_cave_id && $row->exit_cave_id !== $row->entrance_cave_id ? $row->exit?->name : null,
            'people' => collect($row->participants ?? [])->map(fn ($p) => $p['name'].($p['status'] === ParticipantMatcher::GUEST ? ' (guest)' : ''))->implode(', ') ?: null,
            'issues' => collect($row->issues ?? [])->pluck('message')->all() ?: null,
        ];

        if ($full) {
            $brief += [
                'name' => $row->name,
                'duration_minutes' => $row->duration_minutes,
                'start_time' => $row->start_time,
                'visibility' => $this->effectiveVisibility($row),
                'description' => $row->description ? mb_strimwidth($row->description, 0, 300, '…') : null,
                'source_line' => $row->source_line,
            ];
        }

        return array_filter($brief, fn ($v) => $v !== null);
    }

    private function createTrip(TripImportRow $row, User $user, ParticipantMatcher $matcher): Trip
    {
        $system = CaveSystem::findOrFail($row->cave_system_id);
        $entrance = Cave::findOrFail($row->entrance_cave_id);

        $tagIds = [];
        $guestNames = [];
        foreach ($row->participants ?? [] as $person) {
            if ($person['status'] === ParticipantMatcher::MATCHED && $person['user_id'] && $matcher->canTag($person['user_id'])) {
                $tagIds[] = $person['user_id'];
            } else {
                // Includes anyone whose tagging permission changed since staging
                $guestNames[] = $person['name'];
            }
        }

        $description = (string) $row->description;
        if ($guestNames !== []) {
            $description .= ($description === '' ? '' : "\n\n---\n").'*Also on the trip (not on Subterra): '.implode(', ', $guestNames).'*';
        }

        $date = $row->date->format('Y-m-d');
        $start = Carbon::parse($date.' '.($row->start_time ?? '12:00'), 'Europe/London')->utc();
        $end = $row->duration_minutes ? $start->copy()->addMinutes($row->duration_minutes) : null;

        $visibility = $this->effectiveVisibility($row);
        if ($visibility === 'public' && Trip::caveIsClosed($entrance->id)) {
            $visibility = 'private';
        }

        $trip = Trip::create([
            'name' => $row->name ?? $system->name.' — '.$row->date->format('j M Y'),
            'description' => $description === '' ? null : $description,
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $row->exit_cave_id ?? $entrance->id,
            'start_time' => $start,
            'end_time' => $end,
            'visibility' => $visibility,
        ]);
        $trip->participants()->sync(array_values(array_unique(array_merge([$user->id], $tagIds))));

        return $trip;
    }

    private function findDuplicate(TripImportRow $row, User $user): ?string
    {
        $dayStart = Carbon::parse($row->date->format('Y-m-d'), 'Europe/London')->startOfDay()->utc();
        $dayEnd = $dayStart->copy()->addDay();

        $existing = Trip::query()
            ->where('cave_system_id', $row->cave_system_id)
            ->whereBetween('start_time', [$dayStart, $dayEnd->copy()->subSecond()])
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->when($row->trip_id, fn ($q) => $q->where('id', '!=', $row->trip_id))
            ->first(['id', 'short_id']);
        if ($existing) {
            return "You already have a trip here on this date (/trips/{$existing->short_id}).";
        }

        $earlier = TripImportRow::query()
            ->where('trip_import_id', $row->trip_import_id)
            ->where('row_number', '<', $row->row_number)
            ->where('cave_system_id', $row->cave_system_id)
            ->whereDate('date', $row->date->format('Y-m-d'))
            ->whereNotIn('status', [TripImportRow::STATUS_SKIPPED, TripImportRow::STATUS_IMPORTED])
            ->value('row_number');

        return $earlier !== null ? "Same cave and date as row {$earlier}." : null;
    }

    private function effectiveVisibility(TripImportRow $row): string
    {
        if ($row->visibility !== null) {
            return $row->visibility;
        }
        $row->loadMissing('import');

        return $row->import->default_visibility ?? 'public';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function caveSuggestions(TripImportRow $row, string $code): array
    {
        if (in_array($code, ['entrance_needed', 'exit_needed'], true) && $row->cave_system_id) {
            $system = CaveSystem::find($row->cave_system_id);

            return $system ? [CaveMatcher::describeSystem($system)] : [];
        }

        return array_slice($row->cave_candidates ?? [], 0, 3);
    }

    private function rowLabel(TripImportRow $row): string
    {
        return trim(($row->system->name ?? $row->cave_name_raw ?? '?').', '.($row->date?->format('j M Y') ?? $row->date_raw ?? 'no date'), ', ');
    }

    /**
     * Pending rows whose raw cave name matches, or the listed rows.
     *
     * @param  int[]|null  $rowNumbers
     * @return Collection<int, TripImportRow>
     */
    private function targetRows(TripImport $import, ?string $caveName, ?array $rowNumbers): Collection
    {
        $rows = $this->pendingRows($import, $rowNumbers);
        if ($rowNumbers) {
            return $rows;
        }
        if ($caveName === null || trim($caveName) === '') {
            return collect();
        }
        $needle = CaveMatcher::normalise($caveName);

        return $rows->filter(fn (TripImportRow $r) => CaveMatcher::normalise($r->cave_name_raw ?? $r->entrance_name_raw ?? '(blank)') === $needle)->values();
    }

    /**
     * @param  int[]|null  $rowNumbers
     * @return Collection<int, TripImportRow>
     */
    private function pendingRows(TripImport $import, ?array $rowNumbers): Collection
    {
        return $import->rows()
            ->whereIn('status', TripImportRow::PENDING_STATUSES)
            ->when($rowNumbers, fn ($q) => $q->whereIn('row_number', array_map('intval', $rowNumbers)))
            ->orderBy('row_number')
            ->get()
            ->each(fn (TripImportRow $r) => $r->setRelation('import', $import));
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return array<int, array<string, mixed>>
     */
    private function dedupeParticipants(array $participants): array
    {
        $seen = [];

        return array_values(array_filter($participants, function ($p) use (&$seen) {
            $key = $p['user_id'] ?? 'name:'.mb_strtolower($p['name']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
