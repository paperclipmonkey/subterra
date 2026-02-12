<?php

namespace App\Console\Commands;

use App\Models\Callout;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeOldCallouts extends Command
{
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
                $query->whereNotNull('car_details')
                    ->orWhereNotNull('team_details')
                    ->orWhereNotNull('trip_plan');
            })
            ->get();

        $count = $calloutsToScrub->count();

        if ($count === 0) {
            $this->info('No old callouts require scrubbing.');

            return;
        }

        foreach ($calloutsToScrub as $callout) {
            $callout->update([
                'car_details' => null,
                'team_details' => null,
                'trip_plan' => null,
            ]);
        }

        $this->info("Successfully scrubbed sensitive data from {$count} callouts.");
    }
}
