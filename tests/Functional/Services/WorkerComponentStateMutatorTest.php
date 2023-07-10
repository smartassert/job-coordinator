<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerComponentStateMutator;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerComponentStateMutatorTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private WorkerComponentStateRepository $workerComponentStateRepository;
    private WorkerComponentStateMutator $workerComponentStateMutator;

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

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);
        foreach ($workerComponentStateRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->workerComponentStateRepository = $workerComponentStateRepository;

        $workerComponentStateMutator = self::getContainer()->get(WorkerComponentStateMutator::class);
        \assert($workerComponentStateMutator instanceof WorkerComponentStateMutator);
        $this->workerComponentStateMutator = $workerComponentStateMutator;
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->workerComponentStateMutator::getSubscribedEvents();
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

        $this->workerComponentStateMutator->setOnWorkerStateRetrievedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
        self::assertSame(0, $this->workerComponentStateRepository->count([]));
    }

    /**
     * @dataProvider setOnWorkerStateRetrievedEventSuccessDataProvider
     *
     * @param callable(Job, WorkerComponentStateRepository): void $componentStateCreator
     * @param callable(Job): WorkerComponentState                 $expectedApplicationStateCreator
     * @param callable(Job): WorkerComponentState                 $expectedCompilationStateCreator
     * @param callable(Job): WorkerComponentState                 $expectedExecutionStateCreator
     * @param callable(Job): WorkerComponentState                 $expectedEventDeliveryStateCreator
     */
    public function testSetOnWorkerStateRetrievedEventSuccess(
        callable $componentStateCreator,
        ApplicationState $retrievedApplicationState,
        callable $expectedApplicationStateCreator,
        callable $expectedCompilationStateCreator,
        callable $expectedExecutionStateCreator,
        callable $expectedEventDeliveryStateCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $componentStateCreator($job, $this->workerComponentStateRepository);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $event = new WorkerStateRetrievedEvent($job->id, $machineIpAddress, $retrievedApplicationState);
        $this->workerComponentStateMutator->setOnWorkerStateRetrievedEvent($event);

        self::assertEquals(
            $expectedApplicationStateCreator($job),
            $this->workerComponentStateRepository->find(
                WorkerComponentState::generateId($job->id, WorkerComponentName::APPLICATION)
            )
        );

        self::assertEquals(
            $expectedCompilationStateCreator($job),
            $this->workerComponentStateRepository->find(
                WorkerComponentState::generateId($job->id, WorkerComponentName::COMPILATION)
            )
        );

        self::assertEquals(
            $expectedExecutionStateCreator($job),
            $this->workerComponentStateRepository->find(
                WorkerComponentState::generateId($job->id, WorkerComponentName::EXECUTION)
            )
        );

        self::assertEquals(
            $expectedEventDeliveryStateCreator($job),
            $this->workerComponentStateRepository->find(
                WorkerComponentState::generateId($job->id, WorkerComponentName::EVENT_DELIVERY)
            )
        );
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
            'no pre-existing component states' => [
                'componentStateCreator' => function () {
                },
                'retrievedApplicationState' => $applicationStates[0],
                'expectedApplicationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                        ->setState($applicationStates[0]->applicationState->state)
                        ->setIsEndState($applicationStates[0]->applicationState->isEndState)
                    ;
                },
                'expectedCompilationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                        ->setState($applicationStates[0]->compilationState->state)
                        ->setIsEndState($applicationStates[0]->compilationState->isEndState)
                    ;
                },
                'expectedExecutionStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                        ->setState($applicationStates[0]->executionState->state)
                        ->setIsEndState($applicationStates[0]->executionState->isEndState)
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                        ->setState($applicationStates[0]->eventDeliveryState->state)
                        ->setIsEndState($applicationStates[0]->eventDeliveryState->isEndState)
                    ;
                },
            ],
            'has pre-existing component states, no changes' => [
                'componentStateCreator' => function (
                    Job $job,
                    WorkerComponentStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState($applicationStates[0]->applicationState->state)
                            ->setIsEndState($applicationStates[0]->applicationState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState($applicationStates[0]->compilationState->state)
                            ->setIsEndState($applicationStates[0]->compilationState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                            ->setState($applicationStates[0]->executionState->state)
                            ->setIsEndState($applicationStates[0]->executionState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                            ->setState($applicationStates[0]->eventDeliveryState->state)
                            ->setIsEndState($applicationStates[0]->eventDeliveryState->isEndState)
                    );
                },
                'retrievedApplicationState' => $applicationStates[0],
                'expectedApplicationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                        ->setState($applicationStates[0]->applicationState->state)
                        ->setIsEndState($applicationStates[0]->applicationState->isEndState)
                    ;
                },
                'expectedCompilationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                        ->setState($applicationStates[0]->compilationState->state)
                        ->setIsEndState($applicationStates[0]->compilationState->isEndState)
                    ;
                },
                'expectedExecutionStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                        ->setState($applicationStates[0]->executionState->state)
                        ->setIsEndState($applicationStates[0]->executionState->isEndState)
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                        ->setState($applicationStates[0]->eventDeliveryState->state)
                        ->setIsEndState($applicationStates[0]->eventDeliveryState->isEndState)
                    ;
                },
            ],
            'has pre-existing component states, has changes' => [
                'componentStateCreator' => function (
                    Job $job,
                    WorkerComponentStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState($applicationStates[0]->applicationState->state)
                            ->setIsEndState($applicationStates[0]->applicationState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState($applicationStates[0]->compilationState->state)
                            ->setIsEndState($applicationStates[0]->compilationState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                            ->setState($applicationStates[0]->executionState->state)
                            ->setIsEndState($applicationStates[0]->executionState->isEndState)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                            ->setState($applicationStates[0]->eventDeliveryState->state)
                            ->setIsEndState($applicationStates[0]->eventDeliveryState->isEndState)
                    );
                },
                'retrievedApplicationState' => $applicationStates[1],
                'expectedApplicationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                        ->setState($applicationStates[1]->applicationState->state)
                        ->setIsEndState($applicationStates[1]->applicationState->isEndState)
                    ;
                },
                'expectedCompilationStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                        ->setState($applicationStates[1]->compilationState->state)
                        ->setIsEndState($applicationStates[1]->compilationState->isEndState)
                    ;
                },
                'expectedExecutionStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                        ->setState($applicationStates[1]->executionState->state)
                        ->setIsEndState($applicationStates[1]->executionState->isEndState)
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (Job $job) use ($applicationStates) {
                    return (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                        ->setState($applicationStates[1]->eventDeliveryState->state)
                        ->setIsEndState($applicationStates[1]->eventDeliveryState->isEndState)
                    ;
                },
            ],
        ];
    }
}
