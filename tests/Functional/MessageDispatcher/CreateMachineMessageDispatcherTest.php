<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Event\MessageNotYetHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\MessageDispatcher\CreateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\JobInterface;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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
    #[DataProvider('dispatchSuccessDataProvider')]
    public function testDispatchSuccess(callable $eventCreator): void
    {
        $job = $this->jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJob = new ResultsJob($job->getId(), md5((string) rand()), 'awaiting-events', null);
        $this->resultsJobRepository->save($resultsJob);

        $serializedSuite = new SerializedSuite($job->getId(), md5((string) rand()), 'prepared', true, true);
        $this->serializedSuiteRepository->save($serializedSuite);

        $event = $eventCreator($job);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof SerializedSuiteSerializedEvent);

        $this->dispatcher->dispatch($event);

        $this->assertDispatchedMessage($event->getAuthenticationToken(), $job->getId());
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchSuccessDataProvider(): array
    {
        $resultsJobCreatedEventCreator = function (JobInterface $job) {
            \assert('' !== $job->getId());

            return new ResultsJobCreatedEvent(
                md5((string) rand()),
                $job->getId(),
                ResultsClientJobFactory::createRandom()
            );
        };

        $serializedSuiteSerializedEventCreator = function (JobInterface $job) {
            \assert('' !== $job->getId());

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

    public function testDispatchMachineAlreadyExists(): void
    {
        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $job = $this->jobFactory->createRandom();
        \assert('' !== $job->getId());

        $machine = new Machine($job->getId(), 'up/active', 'active', false);
        $machineRepository->save($machine);

        $event = new ResultsJobCreatedEvent(
            'api token',
            $job->getId(),
            ResultsClientJobFactory::createRandom()
        );

        $this->dispatcher->dispatch($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    public function testReDispatchMachineAlreadyExists(): void
    {
        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $job = $this->jobFactory->createRandom();
        \assert('' !== $job->getId());

        $machine = new Machine($job->getId(), 'up/active', 'active', false);
        $machineRepository->save($machine);

        $message = new CreateMachineMessage('api token', $job->getId());
        $event = new MessageNotYetHandleableEvent($message);

        $this->dispatcher->reDispatch($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(
            new CreateMachineMessage($authenticationToken, $jobId),
            $envelope->getMessage()
        );

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }
}
