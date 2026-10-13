<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class BookingSlackNotifier
{
    public function submitted(Booking $booking): void
    {
        $heading = $booking->status === 'approved' ? '📅 *NEW BOOKING (auto-approved)*' : '📅 *NEW BOOKING REQUEST*';

        $this->send($booking, $heading);
    }

    public function approved(Booking $booking, User $officer): void
    {
        $this->send($booking, '✅ *BOOKING APPROVED*', "Approved by: {$officer->name}");
    }

    public function rejected(Booking $booking, User $officer): void
    {
        $this->send($booking, '❌ *BOOKING REJECTED*', "Rejected by: {$officer->name}\nReason: {$booking->rejection_reason}");
    }

    private function send(Booking $booking, string $heading, ?string $extra = null): void
    {
        try {
            /** @var Permit $permit */
            $permit = $booking->permit;
            /** @var CarbonInterface $date */
            $date = $booking->date;
            $url = url('/admin/bookings?booking='.$booking->short_id);
            $lines = [
                $heading,
                "Permit: *{$permit->name}*",
                'Date: '.$date->format('D j M Y'),
                'Applicant: '.($booking->applicant->name ?? 'Non-Subterra user'),
                "Participants: {$booking->participants}",
                $extra,
                "<{$url}|View booking>",
            ];

            SlackAlert::to('permits')->message(implode("\n", array_filter($lines)));
        } catch (\Exception $e) {
            Log::error('Failed to send booking Slack alert: '.$e->getMessage());
        }
    }
}
