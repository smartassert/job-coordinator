<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class DeliveryAttemptCountStamp implements StampInterface
{
    private int $count = 1;

    public function increment(): void
    {
        ++$this->count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
