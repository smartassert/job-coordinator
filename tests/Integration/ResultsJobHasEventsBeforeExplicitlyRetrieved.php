<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ResultsJob;
use App\Message\GetResultsJobMessage;
use App\Repository\ResultsJobRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\AddEventClientInterface;
use SmartAssert\ResultsClient\Model\Event as ResultsClientEvent;
use SmartAssert\ResultsClient\Model\ResourceReference as ResultsClientResourceReference;
use webignition\WaitFor\WaitFor;

class ResultsJobHasEventsBeforeExplicitlyRetrieved extends AbstractCreateJobSuccessSetup
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

    public function testResultsJobHasEventsBeforeEverPollingForChanges(): void
    {
        $messageDelays = $this->getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $getResultsJobMessageDelay = $messageDelays[GetResultsJobMessage::class];
        \assert(is_int($getResultsJobMessageDelay));

        $this->waitUntilResultsJobExists($getResultsJobMessageDelay / 3);

        $resultsJob = $this->getResultsJob();
        self::assertInstanceOf(ResultsJob::class, $resultsJob);

        $resultsAddEventClient = self::getContainer()->get(AddEventClientInterface::class);
        \assert($resultsAddEventClient instanceof AddEventClientInterface);

        $eventAddUrl = str_replace(
            'http://results-http/',
            'http://localhost:9081/',
            $resultsJob->eventAddUrl
        );

        $resultsAddEventClient->add(
            $eventAddUrl,
            new ResultsClientEvent(
                1,
                'job/started',
                new ResultsClientResourceReference(
                    'label',
                    'reference',
                ),
                [],
            ),
        );

        $this->waitUntilResultsJobHasEvents($getResultsJobMessageDelay / 3);

        $resultsJob = $this->getResultsJob();
        self::assertInstanceOf(ResultsJob::class, $resultsJob);

        self::assertTrue($resultsJob->hasEvents());
    }

    private function waitUntilResultsJobExists(int $waitThresholdInSeconds): void
    {
        new WaitFor()->waitFor(
            $waitThresholdInSeconds,
            function () {
                return null !== $this->getResultsJob();
            }
        );
    }

    private function waitUntilResultsJobHasEvents(int $waitThresholdInSeconds): void
    {
        new WaitFor()->waitFor(
            $waitThresholdInSeconds,
            function () {
                $resultsJob = $this->getResultsJob();

                return !(null === $resultsJob || false === $resultsJob->hasEvents());
            }
        );
    }

    private function getResultsJob(): ?ResultsJob
    {
        $job = $this->getJob();
        if (null === $job) {
            return null;
        }

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        return $resultsJobRepository->findOneBy(['jobId' => $job->getId()]);
    }
}
