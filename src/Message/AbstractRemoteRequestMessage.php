<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;

abstract class AbstractRemoteRequestMessage implements JobRemoteRequestMessageInterface
{
    /**
     * @var int<0, max>
     */
    private int $index = 0;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function isRepeatable(): bool
    {
        return RemoteRequestAction::RETRIEVE === $this->getRemoteRequestType()->action;
    }
}
