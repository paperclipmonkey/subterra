<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Auditing smoke-tests.
 *
 * These tests were added after a production incident where audits.auditable_id
 * was still a bigint column on PostgreSQL while User.id had been changed to a
 * 7-character string.  SQLite's loose typing masked the problem in CI: it
 * silently stored the string value in the bigint column.  PostgreSQL rejected
 * it with "invalid input syntax for type bigint".
 *
 * The tests below ensure that:
 *   1. Audit records are actually written when a User is created or updated.
 *   2. auditable_id is stored as the model's real string key, not truncated to
 *      an integer (which would produce '0' on a type mismatch).
 *
 * When run against PostgreSQL these tests also act as a schema smoke-test: if
 * the migration that widens auditable_id to VARCHAR has not been applied, the
 * insert will throw and the test will fail.
 */
class AuditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_writes_an_audit_record(): void
    {
        // Do NOT use Event::fake() — auditing must actually fire and persist.
        $user = User::factory()->create();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'created',
        ]);
    }

    public function test_audit_record_stores_user_id_as_string_not_zero(): void
    {
        $user = User::factory()->create();

        $audit = DB::table('audits')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit, 'No audit record found for user creation');

        // On a type-mismatch (bigint column, string value) PostgreSQL would
        // throw; SQLite would silently cast the string to 0.  Either way the
        // stored value would not equal the original string ID.
        $this->assertSame(
            $user->id,
            $audit->auditable_id,
            'auditable_id was not stored as the user\'s string ID — '.
            'check that the audits.auditable_id column is varchar, not bigint',
        );
    }

    public function test_updating_a_user_writes_an_audit_record(): void
    {
        $user = User::factory()->create();

        $user->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'updated',
        ]);
    }
}
