<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAuditExclusionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function phone_verification_codes_are_not_audited(): void
    {
        config(['audit.console' => true]);
        $user = User::factory()->create();

        $user->forceFill([
            'phone_verification_code' => 'hash-of-123456',
            'name' => 'Renamed',
        ])->save();

        $audits = DB::table('audits')->where('auditable_id', (string) $user->id)->get();
        $this->assertNotEmpty($audits);
        foreach ($audits as $audit) {
            $this->assertStringNotContainsString('hash-of-123456', (string) $audit->new_values);
        }
        $this->assertTrue($audits->contains(fn ($a) => str_contains((string) $a->new_values, 'Renamed')));
    }
}
