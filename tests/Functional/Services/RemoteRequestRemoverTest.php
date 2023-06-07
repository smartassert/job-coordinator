<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestRemover;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestRemoverTest extends WebTestCase
{
    private RemoteRequestRemover $remoteRequestRemover;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestRemover = self::getContainer()->get(RemoteRequestRemover::class);
        \assert($remoteRequestRemover instanceof RemoteRequestRemover);
        $this->remoteRequestRemover = $remoteRequestRemover;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }
        $this->remoteRequestRepository = $remoteRequestRepository;

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $remoteRequestFailureRepository->remove($entity);
        }
        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->remoteRequestRemover);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->remoteRequestRemover::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
    {
        return [
            MachineIsActiveEvent::class => [
                'expectedListenedForEvent' => MachineIsActiveEvent::class,
                'expectedMethod' => 'removeMachineCreateRemoteRequestsForMachineIsActiveEvent',
            ],
        ];
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(string): RemoteRequest[]                         $expectedRemoteRequestCreator
     */
    public function testRemoveForJobAndTypeNoJob(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestCreator,
    ): void {
        $jobId = md5((string) rand());

        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($jobId, $remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $this->remoteRequestRemover->removeForJobAndType($jobId, $type);

        $expectedRemoteRequests = $expectedRemoteRequestCreator($jobId);

        self::assertEquals($expectedRemoteRequests, $this->remoteRequestRepository->findAll());
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     * @dataProvider noRemoteRequestsForMachineCreateDataProvider
     * @dataProvider singleRequestForMachineCreateDataProvider
     * @dataProvider multipleRequestsForMachineCreateDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(string): RemoteRequest[]                         $expectedRemoteRequestCreator
     */
    public function testRemoveForJobAndType(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($job->id, $remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $this->remoteRequestRemover->removeForJobAndType($job->id, $type);
        $expectedRemoteRequests = $expectedRemoteRequestCreator($job->id);

        self::assertEquals($expectedRemoteRequests, $this->remoteRequestRepository->findAll());
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     * @dataProvider noRemoteRequestsForMachineCreateDataProvider
     * @dataProvider singleRequestForMachineCreateDataProvider
     * @dataProvider multipleRequestsForMachineCreateDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(string): RemoteRequest[]                         $expectedRemoteRequestCreator
     */
    public function testRemoveMachineCreateRemoteRequestsForMachineIsActiveEvent(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($job->id, $remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $event = new MachineIsActiveEvent('authentication token', $job->id, '127.0.0.1');

        $this->remoteRequestRemover->removeMachineCreateRemoteRequestsForMachineIsActiveEvent($event);
        $expectedRemoteRequests = $expectedRemoteRequestCreator($job->id);

        self::assertEquals($expectedRemoteRequests, $this->remoteRequestRepository->findAll());
    }

    /**
     * @return array<mixed>
     */
    public function noRemoteRequestsDataProvider(): array
    {
        return [
            'no remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestsCreator' => function () {
                    return [];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function noRemoteRequestsForMachineCreateDataProvider(): array
    {
        return [
            'no remote requests for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function singleRequestForMachineCreateDataProvider(): array
    {
        return [
            'single remote request for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function multipleRequestsForMachineCreateDataProvider(): array
    {
        return [
            'multiple remote requests for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 2),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
        ];
    }
}
