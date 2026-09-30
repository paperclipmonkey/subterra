<?php

declare(strict_types=1);

namespace Tests\Unit\Octane;

use App\Octane\RollBackOpenTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Octane\Events\RequestTerminated;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RollBackOpenTransactionsTest extends TestCase
{
    #[Test]
    public function it_rolls_back_a_transaction_a_request_left_open(): void
    {
        config(['database.connections.leftover' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $connection = DB::connection('leftover');
        $connection->beginTransaction();
        $connection->beginTransaction();

        (new RollBackOpenTransactions())->handle(new RequestTerminated($this->app, $this->app, Request::create('/'), new Response()));

        $this->assertSame(0, $connection->transactionLevel());
    }
}
