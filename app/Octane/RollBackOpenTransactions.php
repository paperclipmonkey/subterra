<?php

declare(strict_types=1);

namespace App\Octane;

use Illuminate\Support\Facades\Log;

/**
 * Octane keeps database connections open between requests, so a transaction a
 * request left open would swallow every later write on that worker.
 */
class RollBackOpenTransactions
{
    public function handle(object $event): void
    {
        foreach ($event->sandbox->make('db')->getConnections() as $name => $connection) {
            if ($connection->transactionLevel() > 0) {
                Log::warning('Rolled back a transaction left open by a request', ['connection' => $name]);
                $connection->rollBack(0);
            }
        }
    }
}
