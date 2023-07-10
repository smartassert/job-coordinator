<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Entity\WorkerState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerState as WorkerStateModel;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerStateRepository;
use App\Services\WorkerStateFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerStateFactoryTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private WorkerStateRepository $workerStateRepository;
    private WorkerComponentStateRepository $workerComponentStateRepository;
    private WorkerStateFactory $workerStateFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

        $workerStateRepository = self::getContainer()->get(WorkerStateRepository::class);
        \assert($workerStateRepository instanceof WorkerStateRepository);
        foreach ($workerStateRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->workerStateRepository = $workerStateRepository;

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);
        foreach ($workerComponentStateRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->workerComponentStateRepository = $workerComponentStateRepository;

        $workerStateFactory = self::getContainer()->get(WorkerStateFactory::class);
        \assert($workerStateFactory instanceof WorkerStateFactory);
        $this->workerStateFactory = $workerStateFactory;
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->workerStateFactory::getSubscribedEvents();
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
            WorkerStateRetrievedEvent::class => [
                'expectedListenedForEvent' => WorkerStateRetrievedEvent::class,
                'expectedMethod' => 'setOnWorkerStateRetrievedEvent',
            ],
        ];
    }

    public function testSetOnWorkerStateRetrievedEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $event = new WorkerStateRetrievedEvent(
            md5((string) rand()),
            md5((string) rand()),
            \Mockery::mock(ApplicationState::class)
        );

        $this->workerStateFactory->setOnWorkerStateRetrievedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    /**
     * @dataProvider setOnWorkerStateRetrievedEventSuccessDataProvider
     *
     * @param callable(Job, WorkerStateRepository): void $workerStateCreator
     * @param callable(Job): WorkerState                 $expectedWorkerStateCreator
     */
    public function testSetOnWorkerStateRetrievedEventSuccess(
        callable $workerStateCreator,
        ApplicationState $retrievedApplicationState,
        callable $expectedWorkerStateCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $workerStateCreator($job, $this->workerStateRepository);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $event = new WorkerStateRetrievedEvent($job->id, $machineIpAddress, $retrievedApplicationState);
        $this->workerStateFactory->setOnWorkerStateRetrievedEvent($event);

        self::assertEquals($expectedWorkerStateCreator($job), $this->workerStateRepository->find($job->id));
    }

    /**
     * @return array<mixed>
     */
    public function setOnWorkerStateRetrievedEventSuccessDataProvider(): array
    {
        $applicationStates = [
            new ApplicationState(
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
            ),
            new ApplicationState(
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
                new ComponentState(md5((string) rand()), false),
            ),
        ];

        return [
            'no pre-existing entity' => [
                'workerStateCreator' => function () {
                },
                'retrievedApplicationState' => $applicationStates[0],
                'expectedWorkerStateCreator' => function (Job $job) use ($applicationStates) {
                    return new WorkerState(
                        $job->id,
                        $applicationStates[0]->applicationState->state,
                        $applicationStates[0]->compilationState->state,
                        $applicationStates[0]->executionState->state,
                        $applicationStates[0]->eventDeliveryState->state,
                    );
                },
            ],
            'has pre-existing entity, no changes' => [
                'workerStateCreator' => function (
                    Job $job,
                    WorkerStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(new WorkerState(
                        $job->id,
                        $applicationStates[0]->applicationState->state,
                        $applicationStates[0]->compilationState->state,
                        $applicationStates[0]->executionState->state,
                        $applicationStates[0]->eventDeliveryState->state,
                    ));
                },
                'retrievedApplicationState' => $applicationStates[0],
                'expectedWorkerStateCreator' => function (Job $job) use ($applicationStates) {
                    return new WorkerState(
                        $job->id,
                        $applicationStates[0]->applicationState->state,
                        $applicationStates[0]->compilationState->state,
                        $applicationStates[0]->executionState->state,
                        $applicationStates[0]->eventDeliveryState->state,
                    );
                },
            ],
            'has pre-existing entity, has changes' => [
                'workerStateCreator' => function (
                    Job $job,
                    WorkerStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(new WorkerState(
                        $job->id,
                        $applicationStates[0]->applicationState->state,
                        $applicationStates[0]->compilationState->state,
                        $applicationStates[0]->executionState->state,
                        $applicationStates[0]->eventDeliveryState->state,
                    ));
                },
                'retrievedApplicationState' => $applicationStates[1],
                'expectedWorkerStateCreator' => function (Job $job) use ($applicationStates) {
                    return new WorkerState(
                        $job->id,
                        $applicationStates[1]->applicationState->state,
                        $applicationStates[1]->compilationState->state,
                        $applicationStates[1]->executionState->state,
                        $applicationStates[1]->eventDeliveryState->state,
                    );
                },
            ],
        ];
    }

    /**
     * @dataProvider createForJobDataProvider
     *
     * @param callable(Job, WorkerComponentStateRepository): void $componentStatesCreator
     * @param callable(Job): WorkerStateModel                     $expectedWorkerStateCreator
     */
    public function testCreateForJob(
        callable $componentStatesCreator,
        callable $expectedWorkerStateCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $componentStatesCreator($job, $this->workerComponentStateRepository);

        $workerState = $this->workerStateFactory->createForJob($job);

        self::assertEquals($expectedWorkerStateCreator($job), $workerState);
    }

    /**
     * @return array<mixed>
     */
    public function createForJobDataProvider(): array
    {
        return [
            'no component state entities' => [
                'componentStatesCreator' => function () {
                },
                'expectedWorkerStateCreator' => function () {
                    return new WorkerStateModel(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'application component state entity only' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerStateModel(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('awaiting-job')
                            ->setIsEndState(false),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'execution component state entity only' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('awaiting')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerStateModel(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('awaiting')
                            ->setIsEndState(false),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'all component states' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EVENT_DELIVERY,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerStateModel(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EVENT_DELIVERY,
                        ))
                            ->setState('running')
                            ->setIsEndState(false),
                    );
                },
            ],
        ];
    }
}
