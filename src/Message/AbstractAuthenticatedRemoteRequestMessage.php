<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractAuthenticatedRemoteRequestMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        string $jobId,
    ) {
        parent::__construct($jobId);
    }
}
