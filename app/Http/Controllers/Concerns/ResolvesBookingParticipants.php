<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalises the named-participant roster submitted against a
 * permit that has `requires_bca` enabled. Each participant must end up with a
 * BCA number: either supplied directly, or resolved from the selected member's
 * profile.
 *
 * @phpstan-type ParticipantRow array{user_id: ?string, name: string, bca_number: string}
 */
trait ResolvesBookingParticipants
{
    /**
     * @return list<array{user_id: ?string, name: string, bca_number: string}>
     *
     * @throws ValidationException
     */
    protected function resolveBcaParticipants(array $input, Permit $permit): array
    {
        $validator = Validator::make($input, [
            'participants_detail' => ['required', 'array', 'min:1'],
            'participants_detail.*.name' => ['required', 'string', 'max:255'],
            'participants_detail.*.user_id' => ['nullable', 'string', 'exists:users,id'],
            'participants_detail.*.bca_number' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{3,20}$/'],
        ]);

        $rows = $validator->validate()['participants_detail'];

        if ($permit->has_max_participants && count($rows) > $permit->max_participants) {
            throw ValidationException::withMessages([
                'participants_detail' => ["This permit allows a maximum of {$permit->max_participants} participants."],
            ]);
        }

        // Resolve BCA numbers for selected members who didn't have one entered.
        $memberIds = collect($rows)->pluck('user_id')->filter()->unique()->values();
        $members = $memberIds->isNotEmpty()
            ? User::whereIn('id', $memberIds)->pluck('bca_number', 'id')
            : collect();

        return collect($rows)->map(function (array $row) use ($members) {
            $name = trim($row['name']);
            $bca = isset($row['bca_number']) ? trim($row['bca_number']) : '';

            if ($bca === '' && !empty($row['user_id'])) {
                $bca = (string) ($members[$row['user_id']] ?? '');
            }

            if ($bca === '') {
                throw ValidationException::withMessages([
                    'participants_detail' => ["A BCA number is required for {$name}."],
                ]);
            }

            return [
                'user_id' => $row['user_id'] ?? null,
                'name' => $name,
                'bca_number' => $bca,
            ];
        })->all();
    }
}
