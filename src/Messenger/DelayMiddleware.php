<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class DelayMiddleware implements MiddlewareInterface
{
    /**
     * @var array<class-string, positive-int>
     */
    private array $delays;

    /**
     * @param array<mixed> $delays
     */
    public function __construct(
        array $delays = []
    ) {
        $this->delays = [];

        foreach ($delays as $messageClass => $delay) {
            if (class_exists($messageClass) && is_int($delay) && $delay > 0) {
                $this->delays[$messageClass] = $delay;
            }
        }
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $messageDelay = $this->delays[$envelope->getMessage()::class] ?? null;
        if (is_int($messageDelay) && $this->shouldAddDelayStamp($envelope)) {
            $envelope = $envelope->with(new DelayStamp($messageDelay));
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function shouldAddDelayStamp(Envelope $envelope): bool
    {
        if (null !== $envelope->last(DelayStamp::class)) {
            return false;
        }

        if (null !== $envelope->last(NonDelayedStamp::class)) {
            return false;
        }

        return true;
    }
}
