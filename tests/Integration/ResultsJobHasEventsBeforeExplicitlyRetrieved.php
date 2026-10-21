<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ResultsJob;
use App\Message\GetResultsJobMessage;
use App\Model\JobInterface;
use App\Repository\ResultsJobRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use SmartAssert\SymfonyRemoteEventRequestFactory\Factory;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;
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

        $job = $this->getJob();
        self::assertInstanceOf(JobInterface::class, $job);

        $resultsJob = $this->getResultsJob();
        self::assertInstanceOf(ResultsJob::class, $resultsJob);

        $payload = [
            'job' => $job->getId(),
            'sequence_number' => 1,
            'type' => 'job/started',
            'label' => $job->getId(),
            'reference' => md5($job->getId()),
        ];

        $event = new RemoteEvent(
            name: 'worker.event',
            id: (string) new Ulid(),
            payload: $payload,
        );

        $remoteEventRequestFactory = self::getContainer()->get(Factory::class);
        \assert($remoteEventRequestFactory instanceof Factory);

        $httpClient = self::getContainer()->get('app.services.http-client.results-client');
        \assert($httpClient instanceof Client);

        $eventNotifyUrl = str_replace(
            'http://results-http/',
            'http://localhost:9081/',
            $resultsJob->eventNotifyUrl,
        );

        $addEventResponse = $httpClient->send(new Request(
            'POST',
            $eventNotifyUrl,
            $remoteEventRequestFactory->createHeaders($event, $resultsJob->eventNotifyToken),
            $remoteEventRequestFactory->createBody($event, $resultsJob->eventNotifyToken),
        ));

        self::assertSame(202, $addEventResponse->getStatusCode());

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
