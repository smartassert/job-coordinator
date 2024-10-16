<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\SourcesClient\Model\SerializedSuite;

class SourcesClientSerializedSuiteFactory
{
    /**
     * @param non-empty-string $serializedSuiteId
     */
    public static function create(string $serializedSuiteId): SerializedSuite
    {
        return new SerializedSuite(
            $serializedSuiteId,
            md5((string) rand()),
            [],
            'requested',
            false,
            false,
            null,
            null
        );
    }
}
