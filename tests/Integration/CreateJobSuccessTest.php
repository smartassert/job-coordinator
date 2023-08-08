<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractCreateJobSuccessTest;
use SmartAssert\SourcesClient\FileClient;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
use Symfony\Component\Uid\Ulid;

class CreateJobSuccessTest extends AbstractCreateJobSuccessTest
{
    use GetClientAdapterTrait;

    protected static function createSuiteId(): string
    {
        $sourceClient = self::getContainer()->get(SourceClient::class);
        \assert($sourceClient instanceof SourceClient);

        $fileSourceLabel = (string) new Ulid();
        \assert('' !== $fileSourceLabel);

        $fileSource = $sourceClient->createFileSource(self::$apiToken, $fileSourceLabel);

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);

        $fileClient->add(self::$apiToken, $fileSource->getId(), 'test.yaml', '- test file content');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);

        $suiteLabel = (string) new Ulid();
        \assert('' !== $suiteLabel);

        $suite = $suiteClient->create(self::$apiToken, $fileSource->getId(), $suiteLabel, ['test.yaml']);

        return $suite->getId();
    }
}
