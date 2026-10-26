<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes a deleted user's personal data from the audit trail (UK GDPR erasure).
 *
 * - Audits OF the user (their own account record: profile old/new values, IP,
 *   user agent) are deleted outright.
 * - Audits BY the user on other records (e.g. a cave edit) are kept, since they
 *   describe shared data, but are de-identified: the author, IP address and user
 *   agent are cleared.
 *
 * audits.auditable_id and audits.user_id are VARCHAR columns, so every id is
 * compared as a string — PostgreSQL has no implicit varchar = integer cast (see
 * StringKeyMorphMany and AGENTS.md).
 */
class UserAuditPurger
{
    /**
     * @return array{deleted: int, anonymised: int}
     */
    public static function purge(string $userId): array
    {
        $morphClass = (new User())->getMorphClass();

        $deleted = DB::table(self::table())
            ->where('auditable_type', $morphClass)
            ->where('auditable_id', $userId)
            ->delete();

        $anonymised = DB::table(self::table())
            ->where('user_type', $morphClass)
            ->where('user_id', $userId)
            ->update([
                'user_id' => null,
                'user_type' => null,
                'ip_address' => null,
                'user_agent' => null,
            ]);

        return ['deleted' => $deleted, 'anonymised' => $anonymised];
    }

    /**
     * Purge audits left behind by users that no longer exist — a safety net for
     * deletions that happened while auditing was disabled (so the
     * {@see \App\Listeners\PurgeDeletedUserAudits} listener never ran), or before
     * it existed.
     *
     * @return array{deleted: int, anonymised: int}
     */
    public static function purgeOrphans(): array
    {
        $morphClass = (new User())->getMorphClass();
        $users = DB::table('users')->select('id');

        $deleted = DB::table(self::table())
            ->where('auditable_type', $morphClass)
            ->whereNotIn('auditable_id', $users)
            ->delete();

        $anonymised = DB::table(self::table())
            ->where('user_type', $morphClass)
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $users)
            ->update([
                'user_id' => null,
                'user_type' => null,
                'ip_address' => null,
                'user_agent' => null,
            ]);

        return ['deleted' => $deleted, 'anonymised' => $anonymised];
    }

    public static function table(): string
    {
        return (string) config('audit.drivers.database.table', 'audits');
    }
}
