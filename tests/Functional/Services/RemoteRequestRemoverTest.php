<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestRemover;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
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
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'removeResultsCreateRemoteRequestsForResultsJobCreatedEvent',
            ],
        ];
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    public function testRemoveForJobAndTypeNoJob(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $jobId = md5((string) rand());

        $this->doRemoteRequestRemoverTest(
            $jobId,
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($jobId, $type) {
                $this->remoteRequestRemover->removeForJobAndType($jobId, $type);
            }
        );
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     * @dataProvider noRemoteRequestsForMachineCreateDataProvider
     * @dataProvider noRemoteRequestsForResultsCreateDataProvider
     * @dataProvider singleRequestForMachineCreateDataProvider
     * @dataProvider singleRequestForResultsCreateDataProvider
     * @dataProvider multipleRequestsForMachineCreateDataProvider
     * @dataProvider multipleRequestsForResultsCreateDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    public function testRemoveForJobAndType(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $this->doRemoteRequestRemoverTest(
            $job->id,
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($job, $type) {
                $this->remoteRequestRemover->removeForJobAndType($job->id, $type);
            }
        );
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     * @dataProvider noRemoteRequestsForMachineCreateDataProvider
     * @dataProvider singleRequestForMachineCreateDataProvider
     * @dataProvider multipleRequestsForMachineCreateDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    public function testRemoveMachineCreateRemoteRequestsForMachineIsActiveEvent(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $this->doRemoteRequestRemoverTest(
            $job->id,
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($job) {
                $this->remoteRequestRemover->removeMachineCreateRemoteRequestsForMachineIsActiveEvent(
                    new MachineIsActiveEvent('authentication token', $job->id, '127.0.0.1')
                );
            }
        );
    }

    /**
     * @dataProvider noRemoteRequestsDataProvider
     * @dataProvider noRemoteRequestsForResultsCreateDataProvider
     * @dataProvider singleRequestForResultsCreateDataProvider
     * @dataProvider multipleRequestsForResultsCreateDataProvider
     *
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    public function testRemoveResultsCreateRemoteRequestsForResultsJobCreatedEvent(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $this->doRemoteRequestRemoverTest(
            $job->id,
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($job) {
                $this->remoteRequestRemover->removeResultsCreateRemoteRequestsForResultsJobCreatedEvent(
                    new ResultsJobCreatedEvent('authentication token', $job->id, \Mockery::mock(ResultsJob::class))
                );
            }
        );
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
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
    public function noRemoteRequestsForResultsCreateDataProvider(): array
    {
        return [
            'no remote requests for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
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
            'single remote request for machine/create, no remote request failure' => [
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
            'single remote request for machine/create, has remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(md5((string) rand()), RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        (new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
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
    public function singleRequestForResultsCreateDataProvider(): array
    {
        return [
            'single remote request for results/create, no remote request failure' => [
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
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                    ];
                },
            ],
            'single remote request for results/create, has remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(md5((string) rand()), RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                    ];
                },
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
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
            'multiple remote requests for machine/create, no remote request failures' => [
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by single remote request' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(md5((string) rand()), RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        (new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by multiple remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure('1', RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        (new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure('1', RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
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
    public function multipleRequestsForResultsCreateDataProvider(): array
    {
        return [
            'multiple remote requests for results/create, no remote request failures' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 2),
                    ];
                },
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
            'multiple remote requests for results/create, remote request failure used by single remote request' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(md5((string) rand()), RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        (new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by multiple remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure('1', RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                        (new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure('1', RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_READ, 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    private function doRemoteRequestRemoverTest(
        string $jobId,
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
        callable $action,
    ): void {
        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($jobId, $remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $action();

        self::assertEquals($expectedRemoteRequestFailuresCreator(), $this->remoteRequestFailureRepository->findAll());
        self::assertEquals(
            $expectedRemoteRequestsCreator($jobId, $remoteRequestFailures),
            $this->remoteRequestRepository->findAll()
        );
    }
}
