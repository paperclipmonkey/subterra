<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Callout;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeOldCallouts extends Command
{
    /**
     * Sentinel values written by the scrub. The selection predicate checks against
     * these so already-scrubbed callouts stop matching (columns that are NOT NULL
     * in the schema can't simply be nulled).
     */
    private const SCRUBBED_DESCRIPTION = 'Scrubbed';
    private const SCRUBBED_PARTICIPANT_NAME = 'Scrubbed Participant';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'callouts:purge-sensitive-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrub sensitive personal data from resolved callouts older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cutoffDate = Carbon::now()->subDays(30);

        $calloutsToScrub = Callout::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['resolved', 'cancelled'])
            ->where(function ($query) {
                // Match anything still holding personal data, and only that — checking
                // against the scrub sentinels means an already-scrubbed callout stops
                // matching, so the command converges instead of rewriting them forever.
                $query->where('description', '!=', self::SCRUBBED_DESCRIPTION)
                    ->orWhereNotNull('car_details')
                    ->orWhereNotNull('car_registration')
                    ->orWhereNotNull('car_parking')
                    ->orWhereNotNull('team_details')
                    ->orWhereNotNull('trip_plan')
                    ->orWhereNotNull('location_data')
                    ->orWhereNotNull('request_data')
                    ->orWhereNotNull('cancelled_ip')
                    ->orWhereNotNull('cancelled_user_agent')
                    ->orWhereHas('participants', function ($q) {
                        $q->where('name', '!=', self::SCRUBBED_PARTICIPANT_NAME)
                            ->orWhereNotNull('phone')
                            ->orWhereNotNull('email');
                    });
            })
            ->with('participants')
            ->get();

        $count = $calloutsToScrub->count();

        if ($count === 0) {
            $this->info('No old callouts require scrubbing.');

            return;
        }

        foreach ($calloutsToScrub as $callout) {
            $callout->update([
                // description defaults to the trip_plan text at creation and the
                // remaining fields are personal data (see the Callout model's $hidden
                // docblock), so they are all scrubbed together.
                'description' => self::SCRUBBED_DESCRIPTION,
                'car_details' => null,
                'car_registration' => null,
                'car_parking' => null,
                'team_details' => null,
                'trip_plan' => null,
                'location_data' => null,
                'request_data' => null,
                'cancelled_ip' => null,
                'cancelled_user_agent' => null,
            ]);

            foreach ($callout->participants as $participant) {
                // We keep user_id if present to link to internal profiles,
                // but scrub the ad-hoc contact details.
                $participant->update([
                    'name' => self::SCRUBBED_PARTICIPANT_NAME,
                    'phone' => null,
                    'email' => null,
                ]);
            }
        }

        $this->info("Successfully scrubbed sensitive data from {$count} callouts and their participants.");
    }
}
