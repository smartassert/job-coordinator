<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\MessageDispatcher\CreateMachineMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

class CreateMachineMessageDispatcherTest extends WebTestCase
{
    private CreateMachineMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;
    private ResultsJobRepository $resultsJobRepository;
    private SerializedSuiteRepository $serializedSuiteRepository;

    private JobFactory $jobFactory;

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

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->jobFactory = $jobFactory;

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

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        foreach ($serializedSuiteRepository->findAll() as $serializedSuite) {
            $entityManager->remove($serializedSuite);
        }
        $entityManager->flush();

        $this->serializedSuiteRepository = $serializedSuiteRepository;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(SerializedSuiteSerializedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    /**
     * @param callable(JobInterface): object $eventCreator
     */
    #[DataProvider('dispatchImmediatelySuccessDataProvider')]
    public function testDispatchImmediatelySuccess(callable $eventCreator): void
    {
        $job = $this->jobFactory->createRandom();

        $resultsJob = new ResultsJob($job->getId(), md5((string) rand()), 'awaiting-events', null);
        $this->resultsJobRepository->save($resultsJob);

        $serializedSuite = new SerializedSuite($job->getId(), md5((string) rand()), 'prepared', true, true);
        $this->serializedSuiteRepository->save($serializedSuite);

        $event = $eventCreator($job);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof SerializedSuiteSerializedEvent);

        $this->dispatcher->dispatchImmediately($event);

        $this->assertNonDelayedMessageIsDispatched($event->getAuthenticationToken(), $job->getId());
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchImmediatelySuccessDataProvider(): array
    {
        $resultsJobCreatedEventCreator = function (JobInterface $job) {
            return new ResultsJobCreatedEvent(
                md5((string) rand()),
                $job->getId(),
                ResultsClientJobFactory::createRandom()
            );
        };

        $serializedSuiteSerializedEventCreator = function (JobInterface $job) {
            return new SerializedSuiteSerializedEvent(
                md5((string) rand()),
                $job->getId(),
                md5((string) rand())
            );
        };

        return [
            'ResultsJobCreatedEvent' => [
                'eventCreator' => $resultsJobCreatedEventCreator,
            ],
            'SerializedSuiteSerializedEvent' => [
                'eventCreator' => $serializedSuiteSerializedEventCreator,
            ],
        ];
    }

    public function testDispatchImmediatelyNeverReady(): void
    {
        $jobId = (string) new Ulid();

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForMachineCreation(),
            $jobId,
            MessageHandlingReadiness::NEVER
        );

        $dispatcher = $this->createDispatcher($assessor);

        $event = new SerializedSuiteSerializedEvent('api token', $jobId, 'serialized suite id');

        $dispatcher->dispatchImmediately($event);

        $this->assertNoMessagesAreDispatched();
    }

    public function testRedispatchNeverReady(): void
    {
        $jobId = (string) new Ulid();

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForMachineCreation(),
            $jobId,
            MessageHandlingReadiness::NEVER
        );

        $dispatcher = $this->createDispatcher($assessor);

        $message = new CreateMachineMessage('api token', $jobId);
        $event = new MessageNotHandleableEvent($message, MessageHandlingReadiness::EVENTUALLY);

        $dispatcher->redispatch($event);

        $this->assertNoMessagesAreDispatched();
    }

    #[DataProvider('redispatchSuccessDataProvider')]
    public function testRedispatchSuccess(MessageHandlingReadiness $readiness): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForMachineCreation(),
            $job->getId(),
            $readiness
        );

        $dispatcher = $this->createDispatcher($assessor);

        $message = new CreateMachineMessage('api token', $job->getId());
        $event = new MessageNotHandleableEvent($message, MessageHandlingReadiness::EVENTUALLY);

        $dispatcher->redispatch($event);

        $this->assertDelayedMessageIsDispatched($message->authenticationToken, $job->getId());
    }

    /**
     * @return array<mixed>
     */
    public static function redispatchSuccessDataProvider(): array
    {
        return [
            'ready now' => [
                'readiness' => MessageHandlingReadiness::NOW,
            ],
            'ready eventually' => [
                'readiness' => MessageHandlingReadiness::EVENTUALLY,
            ],
        ];
    }

    private function createDispatcher(ReadinessAssessorInterface $assessor): CreateMachineMessageDispatcher
    {
        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        return new CreateMachineMessageDispatcher($messageDispatcher, $assessor);
    }

    private function assertNoMessagesAreDispatched(): void
    {
        self::assertSame([], $this->messengerTransport->getSent());
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertNonDelayedMessageIsDispatched(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(new CreateMachineMessage($authenticationToken, $jobId), $envelope->getMessage());
        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDelayedMessageIsDispatched(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(new CreateMachineMessage($authenticationToken, $jobId), $envelope->getMessage());
        self::assertEquals([], $envelope->all(NonDelayedStamp::class));
    }
}
