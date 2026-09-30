<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the higher-privacy defaults applied to accounts belonging to under-18s,
 * as required by the ICO Children's Code (high privacy by default, and not
 * making a child's whereabouts visible to others).
 */
class MinorAccountDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_identifies_an_under_18_account_as_a_minor()
    {
        $child = User::factory()->create(['date_of_birth' => now()->subYears(14)->toDateString()]);

        $this->assertTrue($child->isMinor());
        $this->assertSame(14, $child->age());
        $this->assertTrue($child->hasDeclaredAge());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_treat_an_adult_as_a_minor()
    {
        $adult = User::factory()->create(['date_of_birth' => now()->subYears(30)->toDateString()]);

        $this->assertFalse($adult->isMinor());
        $this->assertSame(30, $adult->age());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_treats_the_eighteenth_birthday_as_adult()
    {
        // Exactly 18 today — no longer a child. The boundary is worth pinning: an
        // off-by-one here would either restrict adults or under-protect 17-year-olds.
        $justEighteen = User::factory()->create(['date_of_birth' => now()->subYears(18)->toDateString()]);
        $dayShort = User::factory()->create(['date_of_birth' => now()->subYears(18)->addDay()->toDateString()]);

        $this->assertFalse($justEighteen->isMinor());
        $this->assertTrue($dayShort->isMinor());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_assume_an_undeclared_age_is_a_minor()
    {
        // Accounts predating the field have a null date of birth. Treating them as
        // children would retroactively restrict the existing membership, so they are
        // not minors — they are simply undeclared, and need prompting.
        $unknown = User::factory()->create(['date_of_birth' => null]);

        $this->assertFalse($unknown->isMinor());
        $this->assertFalse($unknown->hasDeclaredAge());
        $this->assertNull($unknown->age());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_minors_new_trip_defaults_to_club_visibility()
    {
        Event::fake([\App\Events\TripCreated::class]);
        $child = User::factory()->create(['date_of_birth' => now()->subYears(15)->toDateString()]);
        $entrance = Cave::factory()->create();

        $this->actingAs($child);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', [
            'name' => 'Minor Trip',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$child->id],
        ]);

        $response->assertCreated();
        $this->assertSame('club', Trip::where('name', 'Minor Trip')->first()->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_adults_new_trip_still_defaults_to_public()
    {
        Event::fake([\App\Events\TripCreated::class]);
        $adult = User::factory()->create(['date_of_birth' => now()->subYears(40)->toDateString()]);
        $entrance = Cave::factory()->create();

        $this->actingAs($adult);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', [
            'name' => 'Adult Trip',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$adult->id],
        ]);

        $response->assertCreated();
        $this->assertSame('public', Trip::where('name', 'Adult Trip')->first()->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_minor_can_still_choose_to_make_a_trip_public()
    {
        // The default is protective, not a prohibition — an explicit choice is honoured.
        Event::fake([\App\Events\TripCreated::class]);
        $child = User::factory()->create(['date_of_birth' => now()->subYears(16)->toDateString()]);
        $entrance = Cave::factory()->create();

        $this->actingAs($child);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', [
            'name' => 'Chosen Public Trip',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$child->id],
            'visibility' => 'public',
        ]);

        $response->assertCreated();
        $this->assertSame('public', Trip::where('name', 'Chosen Public Trip')->first()->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function declaring_a_minor_date_of_birth_narrows_findability_to_club_only()
    {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'visibility_addable' => 'public',
        ]);

        $this->actingAs($user, 'sanctum');
        // The profile form always echoes back the visibility_addable it loaded, so the
        // narrowing must key off the date-of-birth transition, not the field's absence.
        $response = $this->putJson(route('users.me.update'), [
            'date_of_birth' => now()->subYears(13)->toDateString(),
            'visibility_addable' => 'public',
        ]);

        $response->assertOk();
        $this->assertSame('club', $user->fresh()->visibility_addable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function declaring_an_adult_date_of_birth_leaves_findability_alone()
    {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'visibility_addable' => 'public',
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->putJson(route('users.me.update'), [
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'visibility_addable' => 'public',
        ]);

        $response->assertOk();
        $this->assertSame('public', $user->fresh()->visibility_addable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_minor_deliberately_choosing_public_findability_is_respected()
    {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'visibility_addable' => 'club',
        ]);

        $this->actingAs($user, 'sanctum');
        // Changing the setting away from the stored value in the same request is a
        // deliberate choice, so the minor default must not overwrite it.
        $response = $this->putJson(route('users.me.update'), [
            'date_of_birth' => now()->subYears(17)->toDateString(),
            'visibility_addable' => 'public',
        ]);

        $response->assertOk();
        $this->assertSame('public', $user->fresh()->visibility_addable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_an_implausible_date_of_birth()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->putJson(route('users.me.update'), ['date_of_birth' => now()->addYear()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_of_birth');

        $this->putJson(route('users.me.update'), ['date_of_birth' => '1066-10-14'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_of_birth');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_owner_sees_their_own_date_of_birth_but_other_members_do_not()
    {
        $child = User::factory()->create(['date_of_birth' => now()->subYears(15)->toDateString()]);

        $this->actingAs($child, 'sanctum');
        $own = $this->getJson(route('users.me'));
        $own->assertOk();
        $own->assertJsonPath('data.is_minor', true);
        $own->assertJsonPath('data.date_of_birth', $child->date_of_birth->toDateString());

        // Another member viewing the same profile must see neither the date nor the
        // derived child status — that would disclose it to the whole membership.
        $this->actingAs(User::factory()->create(), 'sanctum');
        $other = $this->getJson(route('users.show', ['user' => $child->id]));
        $other->assertOk();
        $this->assertArrayNotHasKey('date_of_birth', $other->json('data'));
        $this->assertArrayNotHasKey('is_minor', $other->json('data'));
    }
}
