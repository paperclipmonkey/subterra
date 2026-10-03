<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Auditing\UserAuditPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the audit-log retention period.
 *
 * Audits hold personal data (old/new attribute values, IP address, user agent).
 * When a retention period is configured (audit.retention_days; the privacy policy
 * in database/seeders/PrivacyPolicySeeder.php says up to one year), anything older
 * is deleted. It also sweeps
 * audits belonging to users that no longer exist; see UserAuditPurger.
 */
class PruneAudits extends Command
{
    protected $signature = 'audits:prune
        {--days= : Delete audit records older than this many days (default: audit.retention_days; age pruning is skipped when neither is set)}';

    protected $description = 'Delete audit records past the retention period, and audits of deleted users';

    /** Rows deleted per statement, so a large backlog doesn't hold one long lock. */
    private const CHUNK = 1000;

    public function handle(): int
    {
        $configured = $this->option('days') ?? config('audit.retention_days');

        $expired = 0;
        $days = null;
        if ($configured !== null) {
            $days = filter_var($configured, FILTER_VALIDATE_INT);
            if ($days === false || $days < 1) {
                $this->error('--days must be a positive whole number.');

                return self::FAILURE;
            }

            $expired = $this->pruneOlderThan($days);
        }

        $orphans = UserAuditPurger::purgeOrphans();

        $this->info(sprintf(
            '%s; deleted %d and de-identified %d belonging to deleted users.',
            $days === null
                ? 'Age-based pruning is off (set AUDIT_RETENTION_DAYS to enable)'
                : sprintf('Deleted %d audit record(s) older than %d days', $expired, $days),
            $orphans['deleted'],
            $orphans['anonymised'],
        ));

        return self::SUCCESS;
    }

    private function pruneOlderThan(int $days): int
    {
        $cutoff = now()->subDays($days);
        $table = UserAuditPurger::table();

        $expired = 0;
        do {
            $ids = DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $expired += DB::table($table)->whereIn('id', $ids->all())->delete();
        } while ($ids->count() === self::CHUNK);

        return $expired;
    }
}
