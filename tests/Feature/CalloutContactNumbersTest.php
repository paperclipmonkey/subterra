<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CalloutContactNumbersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('callouts.numbers.primary_sms', '+447000000001');
        Config::set('callouts.numbers.backup_sms', '+447000000002');
    }

    public function test_primary_number_is_returned_to_a_normal_user_but_backup_is_hidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/callouts/contact-numbers')
            ->assertOk()
            ->assertJson([
                'primary_sms_number' => '+447000000001',
                'backup_sms_number' => null,
            ]);
    }

    public function test_duty_officer_sees_both_primary_and_backup_numbers()
    {
        $do = User::factory()->dutyOfficer()->create();

        $this->actingAs($do)
            ->getJson('/api/callouts/contact-numbers')
            ->assertOk()
            ->assertJson([
                'primary_sms_number' => '+447000000001',
                'backup_sms_number' => '+447000000002',
            ]);
    }

    public function test_endpoint_requires_authentication()
    {
        $this->getJson('/api/callouts/contact-numbers')->assertUnauthorized();
    }
}
