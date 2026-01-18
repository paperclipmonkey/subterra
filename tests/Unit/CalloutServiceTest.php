<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\OnCallShift;
use App\Services\CalloutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

use App\Services\SmsService;
use Mockery;
use Mockery\MockInterface;

class CalloutServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalloutService $service;
    private User $user;
    private MockInterface $smsServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsServiceMock = Mockery::mock(SmsService::class);
        $this->service = new CalloutService($this->smsServiceMock);
        $this->user = User::factory()->create(['phone' => '1234567890']);
    }

    public function test_creates_callout_when_admin_is_on_call()
    {
        // Arrange: Create a shift covering tomorrow noon
        $tomorrowNoon = now()->addDay()->setHour(12)->setMinute(0);
        $admin = User::factory()->create(['is_admin' => true]);
        
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => $tomorrowNoon->copy()->subHour(),
            'end_at' => $tomorrowNoon->copy()->addHour(),
        ]);

        // Expect SMS to user and participant
        $this->smsServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with('1234567890', Mockery::pattern('/Callout ACTIVE/'));
            
        $this->smsServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with('999', Mockery::pattern('/listed on a callout/'));

        // Act
        $callout = $this->service->create($this->user, [
            'callout_time' => $tomorrowNoon->toDateTimeString(),
            'description' => 'Cave X, Deep Pitch, Me',
            'emergency_contact_name' => 'Mom',
            'emergency_contact_phone' => '123456',
            'participants' => [
                ['name' => 'Bob', 'phone' => '999']
            ]
        ]);

        // Assert
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'description' => 'Cave X, Deep Pitch, Me',
            'status' => 'active',
        ]);
        
        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'name' => 'Bob',
            'phone' => '999',
        ]);
    }

    public function test_fails_when_no_admin_is_on_call()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("No administrator is on-call");

        // Act: Try to create callout for tomorrow noon (no shifts exist)
        $this->service->create($this->user, [
            'callout_time' => now()->addDay()->setHour(12)->toDateTimeString(),
            'description' => 'Should Fail',
            'emergency_contact_name' => 'Mom',
            'emergency_contact_phone' => '123456',
        ]);
    }

    public function test_triggers_callout_and_creates_incident()
    {
        // Arrange: Existing active callout (assuming validation passed previously)
        $callout = \App\Models\Callout::factory()->create([
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        // Act
        $this->service->trigger($callout);

        // Assert
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'triggered',
        ]);

        $this->assertDatabaseHas('incidents', [
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);
    }
}
