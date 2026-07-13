<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\GetSerializedSuiteMessage;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\Generator\StringValue;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\TestSourcesClient\FileClient;
use SmartAssert\TestSourcesClient\FileSourceClient;
use SmartAssert\TestSourcesClient\SuiteClient;

class SerializedSuiteIsPreparedBeforeExplicitlyRetrieved extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;

    private const int MICROSECONDS_PER_SECOND = 1000000;

    public static function tearDownAfterClass(): void
    {
        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllJobs();
        $entityRemover->removeAllRemoteRequests();
    }

    public function testSerializedSuiteIsPreparedBeforeEverPollingForChanges(): void
    {
        $messageDelays = $this->getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $getSerializedSuiteMessageDelay = $messageDelays[GetSerializedSuiteMessage::class];
        \assert(is_int($getSerializedSuiteMessageDelay));

        $this->waitUntilSerializedSuiteStateIsPrepared($getSerializedSuiteMessageDelay);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $serializedSuiteRetrieveRequest = $remoteRequestRepository->findOneBy([
            'jobId' => $this->getJob()?->getId(),
            'type' => 'serialized-suite/retrieve',
        ]);

        self::assertNull($serializedSuiteRetrieveRequest);
    }

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

    private function waitUntilSerializedSuiteStateIsPrepared(int $waitThresholdInSeconds): void
    {
        $waitThreshold = self::MICROSECONDS_PER_SECOND * $waitThresholdInSeconds;
        $totalWaitTime = 0;
        $period = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $stateIsPrepared = false;

        while (false === $stateIsPrepared && $totalWaitTime < $waitThreshold) {
            $totalWaitTime += $period;
            usleep($period);
            $state = $this->getSerializedSuiteState();

            $stateIsPrepared = 'prepared' === $state;
        }

        if ($totalWaitTime >= $waitThreshold) {
            throw new \RuntimeException('Exceeded threshold waiting for serialized suite state to be "prepared"');
        }
    }

    private function getSerializedSuiteState(): ?string
    {
        $job = $this->getJob();
        if (null === $job) {
            return null;
        }

        $foo = self::getContainer()->get(EntityManagerInterface::class);
        \assert($foo instanceof EntityManagerInterface);

        $foo->clear();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuite = $serializedSuiteRepository->findByJobId($job->getId());

        return $serializedSuite?->getState();
    }
}
