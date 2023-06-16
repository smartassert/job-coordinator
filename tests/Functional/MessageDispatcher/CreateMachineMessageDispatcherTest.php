<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\MessageDispatcher\CreateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\Model\Job as ResultsJobModel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateMachineMessageDispatcherTest extends WebTestCase
{
    private CreateMachineMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;
    private JobRepository $jobRepository;
    private ResultsJobRepository $resultsJobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateMachineMessageDispatcher::class);
        \assert($dispatcher instanceof CreateMachineMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        foreach ($jobRepository->findAll() as $job) {
            $entityManager->remove($job);
        }
        $entityManager->flush();

        $this->jobRepository = $jobRepository;

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        foreach ($resultsJobRepository->findAll() as $resultsJob) {
            $entityManager->remove($resultsJob);
        }
        $entityManager->flush();
        $this->resultsJobRepository = $resultsJobRepository;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $remoteRequest) {
            $remoteRequestRepository->remove($remoteRequest);
        }
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(SerializedSuiteSerializedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    /**
     * @dataProvider dispatchMessageNotDispatchedDataProvider
     *
     * @param callable(JobRepository): ?Job              $jobCreator
     * @param callable(?Job, ResultsJobRepository): void $resultsJobCreator
     */
    public function testDispatchMessageNotDispatched(
        callable $jobCreator,
        callable $resultsJobCreator,
        ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event
    ): void {
        $job = $jobCreator($this->jobRepository);
        $resultsJobCreator($job, $this->resultsJobRepository);

        $this->dispatcher->dispatch($event);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @return array<mixed>
     */
    public function dispatchMessageNotDispatchedDataProvider(): array
    {
        $jobId = md5((string) rand());

        $job = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600));

        $jobSerializedSuitePreparing = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600))
            ->setSerializedSuiteState('preparing/running')
        ;

        $resultsJobCreatedEvent = new ResultsJobCreatedEvent(
            md5((string) rand()),
            $jobId,
            \Mockery::mock(ResultsJobModel::class)
        );

        $serializedSuiteSerializedEvent = new SerializedSuiteSerializedEvent(
            md5((string) rand()),
            $jobId,
            md5((string) rand())
        );

        return [
            'ResultsJobCreatedEvent, no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'event' => $serializedSuiteSerializedEvent,
            ],
            'ResultsJobCreatedEvent, no results job' => [
                'jobCreator' => function (JobRepository $jobRepository) use ($job) {
                    $jobRepository->add($job);

                    return $job;
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, no results job' => [
                'jobCreator' => function (JobRepository $jobRepository) use ($job) {
                    $jobRepository->add($job);

                    return $job;
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'event' => $serializedSuiteSerializedEvent,
            ],
            'ResultsJobCreatedEvent, serialized suite state not "prepared"' => [
                'jobCreator' => function (JobRepository $jobRepository) use ($jobSerializedSuitePreparing) {
                    $jobRepository->add($jobSerializedSuitePreparing);

                    return $jobSerializedSuitePreparing;
                },
                'resultsJobCreator' => function (Job $job, ResultsJobRepository $resultsJobRepository) {
                    $resultsJob = new ResultsJob($job->id, md5((string) rand()), 'awaiting-events', null);

                    $resultsJobRepository->save($resultsJob);
                },
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, serialized suite state not "prepared"' => [
                'jobCreator' => function (JobRepository $jobRepository) use ($jobSerializedSuitePreparing) {
                    $jobRepository->add($jobSerializedSuitePreparing);

                    return $jobSerializedSuitePreparing;
                },
                'resultsJobCreator' => function (Job $job, ResultsJobRepository $resultsJobRepository) {
                    $resultsJob = new ResultsJob($job->id, md5((string) rand()), 'awaiting-events', null);

                    $resultsJobRepository->save($resultsJob);
                },
                'event' => $serializedSuiteSerializedEvent,
            ],
        ];
    }

    /**
     * @dataProvider dispatchSuccessDataProvider
     */
    public function testDispatchSuccess(
        Job $job,
        ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event
    ): void {
        $this->jobRepository->add($job);

        $resultsJob = new ResultsJob($job->id, md5((string) rand()), 'awaiting-events', null);
        $this->resultsJobRepository->save($resultsJob);

        $this->dispatcher->dispatch($event);

        $this->assertDispatchedMessage($event->authenticationToken, $job->id);
    }

    /**
     * @return array<mixed>
     */
    public function dispatchSuccessDataProvider(): array
    {
        $jobId = md5((string) rand());

        $job = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600))
            ->setSerializedSuiteState('prepared')
        ;

        $resultsJobCreatedEvent = new ResultsJobCreatedEvent(
            md5((string) rand()),
            $jobId,
            \Mockery::mock(ResultsJobModel::class)
        );

        $serializedSuiteSerializedEvent = new SerializedSuiteSerializedEvent(
            md5((string) rand()),
            $jobId,
            md5((string) rand())
        );

        return [
            'ResultsJobCreatedEvent' => [
                'job' => $job,
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent' => [
                'job' => $job,
                'event' => $serializedSuiteSerializedEvent,
            ],
        ];
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new CreateMachineMessage($authenticationToken, $jobId),
            $envelope->getMessage()
        );

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }
}
