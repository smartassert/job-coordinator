<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\MessageDispatcher\CreateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class CreateWorkerMachineMessageDispatcherTest extends WebTestCase
{
    private CreateMachineMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateMachineMessageDispatcher::class);
        \assert($dispatcher instanceof CreateMachineMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(SerializedSuiteSerializedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    /**
     * @dataProvider dispatchMessageNotDispatchedDataProvider
     */
    public function testDispatchMessageNotDispatched(
        ?Job $job,
        ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event
    ): void {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        if ($job instanceof Job) {
            $this->persistJob($job);
        }

        $mockJobRepository = \Mockery::mock(JobRepository::class);
        $mockJobRepository
            ->shouldReceive('find')
            ->with($event->jobId)
            ->andReturn($job)
        ;

        (new CreateMachineMessageDispatcher($mockJobRepository, $messageBus))->dispatch($event);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @return array<mixed>
     */
    public function dispatchMessageNotDispatchedDataProvider(): array
    {
        $jobId = md5((string) rand());

        $jobResultsJobRequesting = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600))
            ->setResultsJobRequestState(RequestState::REQUESTING)
        ;

        $jobSerializedSuitePreparing = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600))
            ->setResultsToken('results token')
            ->setResultsJobRequestState(null)
            ->setSerializedSuiteState('preparing/running')
        ;

        $resultsJobCreatedEvent = new ResultsJobCreatedEvent(
            md5((string) rand()),
            $jobId,
            \Mockery::mock(ResultsJob::class)
        );

        $serializedSuiteSerializedEvent = new SerializedSuiteSerializedEvent(
            md5((string) rand()),
            $jobId,
            md5((string) rand())
        );

        return [
            'ResultsJobCreatedEvent, no job' => [
                'job' => null,
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, no job' => [
                'job' => null,
                'event' => $serializedSuiteSerializedEvent,
            ],
            'ResultsJobCreatedEvent, results job request state not "succeeded"' => [
                'job' => $jobResultsJobRequesting,
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, results job request state not "succeeded"' => [
                'job' => $jobResultsJobRequesting,
                'event' => $serializedSuiteSerializedEvent,
            ],
            'ResultsJobCreatedEvent, serialized suite state not "prepared"' => [
                'job' => $jobSerializedSuitePreparing,
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent, serialized suite state not "prepared"' => [
                'job' => $jobSerializedSuitePreparing,
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
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $this->persistJob($job);
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
            ->setResultsToken('results token')
            ->setResultsJobRequestState(null)
            ->setSerializedSuiteState('prepared')
        ;

        $resultsJobCreatedEvent = new ResultsJobCreatedEvent(
            md5((string) rand()),
            $jobId,
            \Mockery::mock(ResultsJob::class)
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

    private function persistJob(Job $job): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        if (!$jobRepository instanceof JobRepository) {
            return;
        }

        $existingJob = $jobRepository->find($job->id);
        if ($existingJob instanceof Job) {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            if (!$entityManager instanceof EntityManagerInterface) {
                return;
            }

            $entityManager->remove($existingJob);
            $entityManager->flush();
        }

        $jobRepository->add($job);
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->get();
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
