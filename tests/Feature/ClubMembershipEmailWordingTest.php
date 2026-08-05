<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ClubAccessRequestMail;
use App\Mail\ClubAccessRespondedMail;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Subterra clubs confirm people who are ALREADY members — nobody applies for
 * membership here. The emails used to say "requested to join" / "rejected",
 * which contradicted the UI and misled new members about what was happening.
 */
class ClubMembershipEmailWordingTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        return [
            Club::factory()->create(['name' => 'Mendip Caving Group']),
            User::factory()->create(['name' => 'Ada Caver']),
            User::factory()->create(['name' => 'Club Secretary']),
        ];
    }

    #[Test]
    public function the_admin_email_asks_them_to_confirm_an_existing_member(): void
    {
        [$club, $user, $admin] = $this->fixtures();

        $mail = new ClubAccessRequestMail($club, $user, $admin);
        $mail->build();
        $body = strip_tags($mail->render());

        $this->assertStringContainsString('Confirm a member of Mendip Caving Group', $mail->subject);
        $this->assertStringContainsString('already a member of', $body);
        $this->assertStringContainsString('Confirm Membership', $body);

        $this->assertStringNotContainsString('requested to join', $body);
        $this->assertStringNotContainsString('Review their request', $body);
    }

    #[Test]
    public function the_approval_email_says_the_membership_was_confirmed(): void
    {
        [$club, $user] = $this->fixtures();

        $mail = new ClubAccessRespondedMail($club, $user, 'approved');
        $mail->build();
        $body = strip_tags($mail->render());

        $this->assertStringContainsString('membership of Mendip Caving Group is confirmed', $mail->subject);
        $this->assertStringContainsString('has confirmed you as one of their members', $body);

        $this->assertStringNotContainsString('request to join', $body);
    }

    #[Test]
    public function the_rejection_email_says_the_membership_could_not_be_confirmed(): void
    {
        [$club, $user] = $this->fixtures();

        $mail = new ClubAccessRespondedMail($club, $user, 'rejected');
        $mail->build();
        $body = strip_tags($mail->render());

        $this->assertStringContainsString('could not confirm your membership', $mail->subject);
        $this->assertStringContainsString("wasn't able to confirm your membership", $body);

        $this->assertStringNotContainsString('has been rejected', $body);
        $this->assertStringNotContainsString('request to join', $body);
    }

    #[Test]
    public function the_name_rejection_reason_asks_them_to_seek_confirmation_again(): void
    {
        [$club, $user] = $this->fixtures();

        $mail = new ClubAccessRespondedMail($club, $user, 'rejected', 'incorrect_name');
        $mail->build();
        $body = strip_tags($mail->render());

        $this->assertStringContainsString('Name not recognised', $body);
        $this->assertStringContainsString('confirm your membership again', $body);

        $this->assertStringNotContainsString('re-apply', $body);
    }
}
