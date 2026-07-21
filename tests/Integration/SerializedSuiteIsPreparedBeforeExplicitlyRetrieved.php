<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\GetSerializedSuiteMessage;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use Doctrine\ORM\EntityManagerInterface;
use webignition\WaitFor\WaitFor;

class SerializedSuiteIsPreparedBeforeExplicitlyRetrieved extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;
    use CreateSuiteIdTrait;

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

    private function waitUntilSerializedSuiteStateIsPrepared(int $waitThresholdInSeconds): void
    {
        new WaitFor()->waitFor(
            $waitThresholdInSeconds,
            function () {
                return 'prepared' === $this->getSerializedSuiteState();
            },
        );
    }

    private function getSerializedSuiteState(): ?string
    {
        $job = $this->getJob();
        if (null === $job) {
            return null;
        }

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $entityManager->clear();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuite = $serializedSuiteRepository->findByJobId($job->getId());

        return $serializedSuite?->getState();
    }
}
