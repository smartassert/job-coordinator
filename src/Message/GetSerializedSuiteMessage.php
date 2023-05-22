<?php

declare(strict_types=1);

namespace App\Message;

class GetSerializedSuiteMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $serializedSuiteId,
    ) {
    }
}
