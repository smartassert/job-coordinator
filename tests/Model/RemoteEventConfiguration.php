<?php

declare(strict_types=1);

namespace App\Tests\Model;

readonly class RemoteEventConfiguration
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $headers,
        public string $body,
    ) {}
}
