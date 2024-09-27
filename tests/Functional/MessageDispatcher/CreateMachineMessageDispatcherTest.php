<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\MessageDispatcher\CreateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
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
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(SerializedSuiteSerializedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    /**
     * @param callable(JobFactory): ?Job                      $jobCreator
     * @param callable(?Job, ResultsJobRepository): void      $resultsJobCreator
     * @param callable(?Job, SerializedSuiteRepository): void $serializedSuiteCreator
     * @param callable(?Job): object                          $eventCreator
     */
    #[DataProvider('dispatchMessageNotDispatchedDataProvider')]
    public function testDispatchMessageNotDispatched(
        callable $jobCreator,
        callable $resultsJobCreator,
        callable $serializedSuiteCreator,
        callable $eventCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);
        $resultsJobCreator($job, $this->resultsJobRepository);
        $serializedSuiteCreator($job, $this->serializedSuiteRepository);

        $event = $eventCreator($job);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof SerializedSuiteSerializedEvent);

        $this->dispatcher->dispatch($event);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchMessageNotDispatchedDataProvider(): array
    {
        $resultsJobCreatedEventCreator = function (Job $job) {
            return new ResultsJobCreatedEvent(
                md5((string) rand()),
                $job->id,
                ResultsClientJobFactory::createRandom()
            );
        };

        $serializedSuiteSerializedEventCreator = function (Job $job) {
            return new SerializedSuiteSerializedEvent(
                md5((string) rand()),
                $job->id,
                md5((string) rand())
            );
        };

        $nullCreator = function () {
            return null;
        };

        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        $resultsJobCreator = function (Job $job, ResultsJobRepository $resultsJobRepository) {
            $resultsJob = new ResultsJob($job->id, md5((string) rand()), 'awaiting-events', null);

            $resultsJobRepository->save($resultsJob);
        };

        $serializedSuiteCreatorCreator = function (string $state) {
            return function (Job $job, SerializedSuiteRepository $serializedSuiteRepository) use ($state) {
                \assert('' !== $state);

                $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), $state);
                $serializedSuiteRepository->save($serializedSuite);

                return $serializedSuite;
            };
        };

        return [
            'ResultsJobCreatedEvent, no job' => [
                'jobCreator' => $nullCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new ResultsJobCreatedEvent(
                        md5((string) rand()),
                        $jobId,
                        ResultsClientJobFactory::createRandom()
                    );
                },
            ],
            'SerializedSuiteSerializedEvent, no job' => [
                'jobCreator' => $nullCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new SerializedSuiteSerializedEvent(
                        md5((string) rand()),
                        $jobId,
                        md5((string) rand())
                    );
                },
            ],
            'ResultsJobCreatedEvent, no results job' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $serializedSuiteCreatorCreator('prepared'),
                'eventCreator' => $resultsJobCreatedEventCreator,
            ],
            'SerializedSuiteSerializedEvent, no results job' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $serializedSuiteCreatorCreator('prepared'),
                'eventCreator' => $serializedSuiteSerializedEventCreator,
            ],
            'ResultsJobCreatedEvent, no serialized suite' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $resultsJobCreator,
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'eventCreator' => $resultsJobCreatedEventCreator,
            ],
            'SerializedSuiteSerializedEvent, no serialized suite' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $resultsJobCreator,
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'eventCreator' => $serializedSuiteSerializedEventCreator,
            ],
            'ResultsJobCreatedEvent, serialized suite state not "prepared"' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $resultsJobCreator,
                'serializedSuiteCreator' => $serializedSuiteCreatorCreator('preparing'),
                'event' => $resultsJobCreatedEventCreator,
            ],
            'SerializedSuiteSerializedEvent, serialized suite state not "prepared"' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => $resultsJobCreator,
                'serializedSuiteCreator' => $serializedSuiteCreatorCreator('preparing'),
                'event' => $serializedSuiteSerializedEventCreator,
            ],
        ];
    }

    /**
     * @param callable(Job): object $eventCreator
     */
    #[DataProvider('dispatchSuccessDataProvider')]
    public function testDispatchSuccess(callable $eventCreator): void
    {
        $job = $this->jobFactory->createRandom();

        $resultsJob = new ResultsJob($job->id, md5((string) rand()), 'awaiting-events', null);
        $this->resultsJobRepository->save($resultsJob);

        $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), 'prepared');
        $this->serializedSuiteRepository->save($serializedSuite);

        $event = $eventCreator($job);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof SerializedSuiteSerializedEvent);

        $this->dispatcher->dispatch($event);

        $this->assertDispatchedMessage($event->authenticationToken, $job->id);
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchSuccessDataProvider(): array
    {
        $resultsJobCreatedEventCreator = function (Job $job) {
            return new ResultsJobCreatedEvent(
                md5((string) rand()),
                $job->id,
                ResultsClientJobFactory::createRandom()
            );
        };

        $serializedSuiteSerializedEventCreator = function (Job $job) {
            return new SerializedSuiteSerializedEvent(
                md5((string) rand()),
                $job->id,
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
