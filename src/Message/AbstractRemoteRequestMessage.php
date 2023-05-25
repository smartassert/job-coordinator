<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractRemoteRequestMessage implements JobRemoteRequestMessageInterface
{
    /**
     * @var int<0, max>
     */
    private int $index;

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param int<0, max>      $index
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        int $index,
    ) {
        $this->index = $index;
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
}
