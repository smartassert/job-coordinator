<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Services\Generator\StringValue;
use SmartAssert\TestSourcesClient\FileClient;
use SmartAssert\TestSourcesClient\FileSourceClient;
use SmartAssert\TestSourcesClient\SuiteClient;

trait CreateSuiteIdTrait
{
    protected static function createSuiteId(): string
    {
        $fileSourceClient = self::getContainer()->get(FileSourceClient::class);
        \assert($fileSourceClient instanceof FileSourceClient);

        $fileSourceLabel = StringValue::random();

        $fileSourceId = $fileSourceClient->create(self::$apiToken, $fileSourceLabel);
        \assert(is_string($fileSourceId));

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);

        $fileClient->add(self::$apiToken, $fileSourceId, 'test.yaml', '- test file content');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);

        $suiteLabel = StringValue::random();

        $suiteId = $suiteClient->create(self::$apiToken, $fileSourceId, $suiteLabel, ['test.yaml']);
        \assert(is_string($suiteId));

        return $suiteId;
    }
}
