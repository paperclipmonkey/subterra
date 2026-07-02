<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Contracts\VoiceCaller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyOfficerTestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_self_sends_sms_and_call_to_own_phone()
    {
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->once()->andReturn(true));
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->once()->andReturn('CA1'));

        $do = User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);

        $this->actingAs($do)->postJson('/api/admin/duty-officers/test-self')
            ->assertStatus(200)
            ->assertJsonPath('results.0.sms', true)
            ->assertJsonPath('results.0.call', true);
    }

    public function test_test_self_returns_422_without_a_phone()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => null]);

        $this->actingAs($do)->postJson('/api/admin/duty-officers/test-self')
            ->assertStatus(422);
    }

    public function test_test_broadcast_sends_to_all_duty_officers()
    {
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->andReturn(true));
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->andReturn('CA1'));

        $do1 = User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        User::factory()->dutyOfficer()->create(['phone' => '+447222222222']);
        // A DO without a phone must be skipped.
        User::factory()->dutyOfficer()->create(['phone' => null]);

        $this->actingAs($do1)->postJson('/api/admin/duty-officers/test-broadcast')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Test SMS and voice call sent to 2 duty officer(s).');
    }

    public function test_test_broadcast_includes_platform_admins()
    {
        // Platform admins are accepted rota members, so the confidence test must ring
        // every phone a real widened escalation would (matches CheckOverdueCallouts).
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->andReturn(true));
        $this->mock(VoiceCaller::class, fn ($m) => $m->shouldReceive('call')->andReturn('CA1'));

        $do = User::factory()->dutyOfficer()->create(['phone' => '+447111111111']);
        User::factory()->admin()->create(['phone' => '+447333333333']);

        $this->actingAs($do)->postJson('/api/admin/duty-officers/test-broadcast')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Test SMS and voice call sent to 2 duty officer(s).');
    }

    public function test_non_duty_officer_is_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/admin/duty-officers/test-self')
            ->assertStatus(403);
    }
}
