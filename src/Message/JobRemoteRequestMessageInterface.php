<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

interface JobRemoteRequestMessageInterface
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string;

    public function getRemoteRequestType(): RemoteRequestType;

    /**
     * @return int<0, max>
     */
    public function getIndex(): int;
}
