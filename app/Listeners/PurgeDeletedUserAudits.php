<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Auditing\UserAuditPurger;
use OwenIt\Auditing\Events\Auditing;

/**
 * When a user account is deleted, erase it from the audit trail.
 *
 * Users are hard-deleted (no SoftDeletes), and the auditing package records the
 * deletion itself as a "deleted" audit carrying the account's last attribute
 * values. Hooking the package's pre-write Auditing event for that "deleted"
 * audit lets us:
 *   1. delete every existing audit of the user's own record,
 *   2. de-identify audits the user authored on other records, and
 *   3. veto the "deleted" audit itself (returning false cancels it), so the
 *      deletion doesn't immediately leave a fresh copy of the profile behind.
 *
 * The listener is auto-discovered (app/Listeners), so neither the User model nor
 * the controllers that delete users need to know about it. Deletions made while
 * auditing is disabled never reach it; `audits:prune` sweeps those orphans.
 */
class PurgeDeletedUserAudits
{
    public function handle(Auditing $event): ?bool
    {
        $model = $event->model;

        if (!$model instanceof User || $model->getAuditEvent() !== 'deleted') {
            return null;
        }

        UserAuditPurger::purge((string) $model->getKey());

        return false;
    }
}
