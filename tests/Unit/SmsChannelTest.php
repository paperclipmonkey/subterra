<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Channels\SmsChannel;
use App\Contracts\SmsSender;
use Illuminate\Notifications\Notification;
use Mockery;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    private function notifiableWithPhone(?string $phone)
    {
        return new class ($phone) {
            public function __construct(public ?string $phone)
            {
            }

            public function getKey(): int
            {
                return 1;
            }
        };
    }

    private function stubNotification(): Notification
    {
        return new class () extends Notification {
            public function toSms($notifiable): string
            {
                return 'test message';
            }
        };
    }

    public function test_throws_when_delivery_fails()
    {
        $sender = Mockery::mock(SmsSender::class);
        $sender->shouldReceive('send')->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        (new SmsChannel($sender))->send($this->notifiableWithPhone('+447111111111'), $this->stubNotification());
    }

    public function test_does_not_throw_on_success()
    {
        $sender = Mockery::mock(SmsSender::class);
        $sender->shouldReceive('send')->once()->andReturn(true);

        (new SmsChannel($sender))->send($this->notifiableWithPhone('+447111111111'), $this->stubNotification());
        $this->assertTrue(true);
    }

    public function test_skips_silently_when_no_phone()
    {
        $sender = Mockery::mock(SmsSender::class);
        $sender->shouldNotReceive('send');

        (new SmsChannel($sender))->send($this->notifiableWithPhone(null), $this->stubNotification());
        $this->assertTrue(true);
    }
}
