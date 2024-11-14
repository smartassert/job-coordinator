<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\SourcesClient\Model\SerializedSuite;

class SourcesClientSerializedSuiteFactory
{
    /**
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $suiteId
     */
    public static function create(
        string $serializedSuiteId,
        string $suiteId,
    ): SerializedSuite {
        return new SerializedSuite(
            $serializedSuiteId,
            $suiteId,
            [],
            'requested',
            false,
            false,
            null,
            null
        );
    }
}
