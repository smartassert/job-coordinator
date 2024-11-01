<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

interface JobRemoteRequestMessageInterface
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string;

    public function getRemoteRequestEntity(): RemoteRequestEntity;

    public function getRemoteRequestAction(): RemoteRequestAction;

    public function isRepeatable(): bool;

    /**
     * @return int<0, max>
     */
    public function getIndex(): int;

    /**
     * @param int<0, max> $index
     */
    public function setIndex(int $index): self;
}
