<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UK GDPR: the audit trail must not hoard personal data. Sensitive profile
 * fields are never audited, a deleted account's audits are erased (including
 * the audit of the deletion itself), and audits past the one-year retention
 * period promised in the privacy policy are pruned.
 */
class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['audit.console' => true]);
    }

    // -------------------------------------------------------------------------
    // $auditExclude
    // -------------------------------------------------------------------------

    #[Test]
    public function sensitive_personal_fields_are_never_written_to_the_audit_trail(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
            'phone' => '+447700900111',
            'date_of_birth' => '1990-05-01',
            'bio' => 'Original bio',
        ]);

        $user->forceFill([
            'name' => 'Renamed Caver',
            'email' => 'changed@example.com',
            'phone' => '+447700900222',
            'phone_verified_at' => now(),
            'phone_verification_code' => 'hash-of-123456',
            'phone_verification_sent_at' => now(),
            'phone_verification_attempts' => 2,
            'date_of_birth' => '1985-01-02',
            'bio' => 'Changed bio',
            'remember_token' => 'secret-remember-token',
        ])->save();

        $audits = DB::table('audits')
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', (string) $user->id)
            ->get();

        $this->assertTrue($audits->contains('event', 'created'));
        $this->assertTrue($audits->contains('event', 'updated'));

        $excluded = [
            'email', 'phone', 'phone_verified_at', 'phone_verification_code', 'phone_verification_sent_at',
            'phone_verification_attempts', 'date_of_birth', 'bio', 'photo', 'remember_token',
        ];
        $values = [
            'original@example.com', 'changed@example.com', '+447700900111', '+447700900222',
            '1990-05-01', '1985-01-02', 'Original bio', 'Changed bio', 'hash-of-123456', 'secret-remember-token',
        ];

        foreach ($audits as $audit) {
            $old = json_decode((string) $audit->old_values, true) ?: [];
            $new = json_decode((string) $audit->new_values, true) ?: [];
            foreach ($excluded as $field) {
                $this->assertArrayNotHasKey($field, $old, "{$field} leaked into old_values ({$audit->event})");
                $this->assertArrayNotHasKey($field, $new, "{$field} leaked into new_values ({$audit->event})");
            }
            foreach ($values as $value) {
                $this->assertStringNotContainsString($value, (string) $audit->old_values);
                $this->assertStringNotContainsString($value, (string) $audit->new_values);
            }
        }

        // Non-sensitive changes are still audited.
        $this->assertTrue($audits->contains(fn ($a) => str_contains((string) $a->new_values, 'Renamed Caver')));
    }

    // -------------------------------------------------------------------------
    // Account deletion
    // -------------------------------------------------------------------------

    #[Test]
    public function deleting_an_account_via_the_api_erases_its_audits_including_the_deletion_audit(): void
    {
        $user = User::factory()->create(['name' => 'Leaving Caver']);
        $user->update(['name' => 'Leaving Caver Renamed']);
        $bystander = User::factory()->create();
        $bystander->update(['name' => 'Bystander Renamed']);

        $this->assertGreaterThan(0, $this->auditsOf($user)->count());

        $this->actingAs($user)->deleteJson('/api/users/me')->assertOk();

        $this->assertNull(User::withoutGlobalScopes()->find($user->id));
        $this->assertSame(0, $this->auditsOf($user)->count(), 'Audits of the deleted account remain');
        $this->assertFalse(
            DB::table('audits')->where('event', 'deleted')->where('old_values', 'like', '%Leaving Caver%')->exists(),
            'The deletion wrote a "deleted" audit containing the profile',
        );

        // Other users' audit history is untouched.
        $this->assertGreaterThan(0, $this->auditsOf($bystander)->count());
    }

    #[Test]
    public function an_admin_deleting_a_user_erases_that_users_audits(): void
    {
        $user = User::factory()->create();
        $user->update(['name' => 'Target Renamed']);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertOk();

        $this->assertSame(0, $this->auditsOf($user)->count());
    }

    #[Test]
    public function deleting_a_user_de_identifies_audits_they_authored_on_other_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cave = Cave::factory()->create();

        $authored = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now(), [
            'user_type' => $user->getMorphClass(),
            'user_id' => (string) $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'LeavingBrowser/1.0',
            'new_values' => json_encode(['name' => 'Fixed cave name']),
        ]);
        $othersAudit = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now(), [
            'user_type' => $other->getMorphClass(),
            'user_id' => (string) $other->id,
            'ip_address' => '198.51.100.1',
        ]);

        $user->delete();

        $row = DB::table('audits')->find($authored);
        $this->assertNotNull($row, 'The change to shared data should be kept');
        $this->assertNull($row->user_id);
        $this->assertNull($row->user_type);
        $this->assertNull($row->ip_address);
        $this->assertNull($row->user_agent);
        $this->assertStringContainsString('Fixed cave name', (string) $row->new_values);

        $kept = DB::table('audits')->find($othersAudit);
        $this->assertSame((string) $other->id, (string) $kept->user_id);
        $this->assertSame('198.51.100.1', $kept->ip_address);
    }

    // -------------------------------------------------------------------------
    // audits:prune
    // -------------------------------------------------------------------------

    #[Test]
    public function prune_removes_audits_older_than_the_retention_period_and_keeps_recent_ones(): void
    {
        $cave = Cave::factory()->create();
        DB::table('audits')->delete();

        $old = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(400));
        $justOld = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(366));
        $recent = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(364));
        $today = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now());

        $this->artisan('audits:prune', ['--days' => 365])->assertSuccessful();

        $this->assertNull(DB::table('audits')->find($old));
        $this->assertNull(DB::table('audits')->find($justOld));
        $this->assertNotNull(DB::table('audits')->find($recent));
        $this->assertNotNull(DB::table('audits')->find($today));
    }

    #[Test]
    public function prune_uses_the_configured_retention_period(): void
    {
        $cave = Cave::factory()->create();
        $old = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(370));
        $recent = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(300));

        config(['audit.retention_days' => 365]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertNull(DB::table('audits')->find($old));
        $this->assertNotNull(DB::table('audits')->find($recent));
    }

    #[Test]
    public function prune_keeps_old_audits_when_no_retention_period_is_configured(): void
    {
        // Age-based deletion is opt-in (AUDIT_RETENTION_DAYS): it permanently
        // removes history, so an unconfigured deploy must not start deleting.
        $cave = Cave::factory()->create();
        $old = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now()->subDays(1000));

        config(['audit.retention_days' => null]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertNotNull(DB::table('audits')->find($old));
    }

    #[Test]
    public function prune_sweeps_audits_of_users_deleted_while_auditing_was_off(): void
    {
        $gone = User::factory()->create();
        $kept = User::factory()->create();
        $cave = Cave::factory()->create();
        $goneId = (string) $gone->id;

        // Simulate a deletion that bypassed the listener (auditing disabled).
        User::disableAuditing();
        $gone->delete();
        User::enableAuditing();

        $this->assertGreaterThan(0, DB::table('audits')->where('auditable_id', $goneId)
            ->where('auditable_type', $gone->getMorphClass())->count());
        $authored = $this->insertAudit($cave->getMorphClass(), (string) $cave->id, now(), [
            'user_type' => $gone->getMorphClass(),
            'user_id' => $goneId,
            'ip_address' => '203.0.113.9',
        ]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('audits')->where('auditable_id', $goneId)
            ->where('auditable_type', $gone->getMorphClass())->count());
        $this->assertNull(DB::table('audits')->find($authored)->user_id);
        $this->assertNull(DB::table('audits')->find($authored)->ip_address);
        $this->assertGreaterThan(0, $this->auditsOf($kept)->count());
    }

    #[Test]
    public function prune_rejects_a_non_positive_days_option(): void
    {
        $this->artisan('audits:prune', ['--days' => 0])->assertFailed();
        $this->artisan('audits:prune', ['--days' => 'abc'])->assertFailed();
    }

    #[Test]
    public function prune_is_scheduled_daily(): void
    {
        // Loading the console kernel registers routes/console.php's schedule.
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'audits:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    // -------------------------------------------------------------------------

    private function auditsOf(User $user): \Illuminate\Support\Collection
    {
        return DB::table('audits')
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', (string) $user->id)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertAudit(string $type, string $id, \DateTimeInterface $at, array $overrides = []): int
    {
        return (int) DB::table('audits')->insertGetId(array_merge([
            'event' => 'updated',
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => json_encode([]),
            'new_values' => json_encode(['name' => 'x']),
            'created_at' => $at,
            'updated_at' => $at,
        ], $overrides));
    }
}
