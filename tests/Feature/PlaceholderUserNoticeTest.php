<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\PlaceholderUserCreated;
use App\Mail\PlaceholderUserNoticeMail;
use App\Models\Report;
use App\Models\Scopes\IsActiveScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UK GDPR Article 14: when a member creates an account record for someone else,
 * that person has to be told, and given a way to object.
 */
class PlaceholderUserNoticeTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_a_placeholder_account_notifies_the_person()
    {
        Mail::fake();
        $creator = User::factory()->create(['name' => 'Alice Roberts']);

        $this->actingAs($creator, 'sanctum');
        $this->postJson(route('users.create'), [
            'name' => 'Bob Nguyen',
            'email' => 'bob@example.test',
        ])->assertCreated();

        Mail::assertQueued(PlaceholderUserNoticeMail::class, function ($mail) {
            return $mail->hasTo('bob@example.test')
                && $mail->creator->name === 'Alice Roberts';
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_notice_is_sent_at_creation_not_when_a_trip_is_saved()
    {
        // The placeholder is created the moment a member adds a participant, so
        // abandoning the trip form must not leave an unnotified record behind.
        Mail::fake();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(route('users.create'), [
            'name' => 'Carol Diaz',
            'email' => 'carol@example.test',
        ])->assertCreated();

        // No trip has been created at this point.
        $this->assertSame(0, \App\Models\Trip::count());
        Mail::assertQueued(PlaceholderUserNoticeMail::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_notice_is_only_sent_once()
    {
        Mail::fake();
        $creator = User::factory()->create();
        $subject = User::factory()->create(['placeholder_notice_sent_at' => now()]);

        event(new PlaceholderUserCreated($subject, $creator));

        Mail::assertNothingQueued();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_when_the_notice_was_sent()
    {
        Mail::fake();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(route('users.create'), [
            'name' => 'Dan Evans',
            'email' => 'dan@example.test',
        ])->assertCreated();

        $created = User::withoutGlobalScope(IsActiveScope::class)
            ->where('email', 'dan@example.test')->first();

        $this->assertNotNull($created->placeholder_notice_sent_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function self_signup_does_not_trigger_the_notice()
    {
        // Magic-link signup is the person giving us their own details, so Article
        // 14 does not apply and a "someone added you" email would be nonsense.
        Mail::fake();

        $this->postJson('/api/auth/magic-link', ['email' => 'self@example.test'])->assertOk();

        Mail::assertNotQueued(PlaceholderUserNoticeMail::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_objection_page_needs_a_valid_signature()
    {
        $user = User::factory()->create();

        $this->get('/data-objection/'.$user->id)->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_objection_page_opens_from_a_signed_link()
    {
        $user = User::factory()->create(['email' => 'erin@example.test']);
        $user->forceFill(['is_active' => false])->save();

        $this->get($this->signedObjectionUrl($user))
            ->assertOk()
            ->assertSee('erin@example.test');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function objecting_raises_a_report_and_hides_the_record()
    {
        $user = User::factory()->create(['visibility_addable' => 'public']);

        $this->post($this->signedObjectionUrl($user), [
            'reason' => 'I never asked to be on here.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => null,
            'reportable_type' => User::class,
            'reportable_id' => $user->id,
            'category' => 'data_objection',
            'status' => 'open',
        ]);

        $fresh = User::withoutGlobalScope(IsActiveScope::class)->find($user->id);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame('club', $fresh->visibility_addable);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_objection_flagged_as_a_minor_is_treated_as_urgent()
    {
        $user = User::factory()->create();

        $this->post($this->signedObjectionUrl($user), ['is_minor' => '1'])->assertRedirect();

        $report = Report::where('reportable_id', $user->id)->first();
        $this->assertSame('child_safety', $report->category);
        $this->assertTrue($report->isUrgent());
        $this->assertStringContainsString('under-18', $report->details);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function objecting_twice_does_not_stack_up_reports()
    {
        $user = User::factory()->create();
        $url = $this->signedObjectionUrl($user);

        $this->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();

        $this->assertSame(1, Report::where('reportable_id', $user->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_objection_appears_in_the_moderation_queue()
    {
        $user = User::factory()->create(['name' => 'Fran Okafor']);
        $this->post($this->signedObjectionUrl($user))->assertRedirect();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->getJson(route('admin.reports.index'));

        $response->assertOk();
        $this->assertSame('data_objection', $response->json('data.0.category'));
        // Raised by the subject themselves, so there is no reporter to show.
        $this->assertNull($response->json('data.0.reporter.id'));
        // The objection deactivates the account it is about. The moderator must
        // still see who it concerns rather than a "(deleted)" placeholder.
        $this->assertSame('Fran Okafor', $response->json('data.0.target.label'));
    }

    private function signedObjectionUrl(User $user): string
    {
        return URL::temporarySignedRoute('data-objection', now()->addDays(90), ['user' => $user->id]);
    }
}
