<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerComponentStateMutator;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerClient\Model\ApplicationState as WorkerClientApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState as WorkerClientComponentState;
use SmartAssert\WorkerClient\Model\MetaState as WorkerClientMetaState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

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
        $this->jobRepository = $jobRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

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

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->workerComponentStateMutator::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
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
        $jobCount = $this->jobRepository->count([]);

        $jobId = (string) new Ulid();

        $irrelevantApplicationState = new WorkerClientApplicationState(
            new WorkerClientComponentState('state', new WorkerClientMetaState(false, false)),
            new WorkerClientComponentState('state', new WorkerClientMetaState(false, false)),
            new WorkerClientComponentState('state', new WorkerClientMetaState(false, false)),
            new WorkerClientComponentState('state', new WorkerClientMetaState(false, false)),
        );

        $event = new WorkerStateRetrievedEvent($jobId, md5((string) rand()), $irrelevantApplicationState);

        $this->workerComponentStateMutator->setOnWorkerStateRetrievedEvent($event);

        self::assertSame($jobCount, $this->jobRepository->count([]));
        self::assertSame(0, $this->workerComponentStateRepository->count([]));
    }

    /**
     * @param callable(JobInterface, WorkerComponentStateRepository): void $componentStateCreator
     * @param callable(JobInterface): WorkerComponentState                 $expectedApplicationStateCreator
     * @param callable(JobInterface): WorkerComponentState                 $expectedCompilationStateCreator
     * @param callable(JobInterface): WorkerComponentState                 $expectedExecutionStateCreator
     * @param callable(JobInterface): WorkerComponentState                 $expectedEventDeliveryStateCreator
     */
    #[DataProvider('setOnWorkerStateRetrievedEventSuccessDataProvider')]
    public function testSetOnWorkerStateRetrievedEventSuccess(
        callable $componentStateCreator,
        WorkerClientApplicationState $retrievedApplicationState,
        callable $expectedApplicationStateCreator,
        callable $expectedCompilationStateCreator,
        callable $expectedExecutionStateCreator,
        callable $expectedEventDeliveryStateCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $componentStateCreator($job, $this->workerComponentStateRepository);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $event = new WorkerStateRetrievedEvent($job->getId(), $machineIpAddress, $retrievedApplicationState);
        $this->workerComponentStateMutator->setOnWorkerStateRetrievedEvent($event);

        self::assertEquals(
            $expectedApplicationStateCreator($job),
            $this->workerComponentStateRepository->findOneBy([
                'jobId' => $job->getId(),
                'componentName' => WorkerComponentName::APPLICATION,
            ])
        );

        self::assertEquals(
            $expectedCompilationStateCreator($job),
            $this->workerComponentStateRepository->findOneBy([
                'jobId' => $job->getId(),
                'componentName' => WorkerComponentName::COMPILATION,
            ])
        );

        self::assertEquals(
            $expectedExecutionStateCreator($job),
            $this->workerComponentStateRepository->findOneBy([
                'jobId' => $job->getId(),
                'componentName' => WorkerComponentName::EXECUTION,
            ])
        );

        self::assertEquals(
            $expectedEventDeliveryStateCreator($job),
            $this->workerComponentStateRepository->findOneBy([
                'jobId' => $job->getId(),
                'componentName' => WorkerComponentName::EVENT_DELIVERY,
            ])
        );
    }

    /**
     * @return array<mixed>
     */
    public static function setOnWorkerStateRetrievedEventSuccessDataProvider(): array
    {
        $applicationStates = [
            new WorkerClientApplicationState(
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
            ),
            new WorkerClientApplicationState(
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
                new WorkerClientComponentState(md5((string) rand()), new WorkerClientMetaState(false, false)),
            ),
        ];

        return [
            'no pre-existing component states' => [
                'componentStateCreator' => function () {},
                'retrievedApplicationState' => $applicationStates[0],
                'expectedApplicationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                        ->setState($applicationStates[0]->applicationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->applicationState->metaState->ended,
                            $applicationStates[0]->applicationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedCompilationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION)
                        ->setState($applicationStates[0]->compilationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->compilationState->metaState->ended,
                            $applicationStates[0]->compilationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedExecutionStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION)
                        ->setState($applicationStates[0]->executionState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->executionState->metaState->ended,
                            $applicationStates[0]->executionState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY)
                        ->setState($applicationStates[0]->eventDeliveryState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->eventDeliveryState->metaState->ended,
                            $applicationStates[0]->eventDeliveryState->metaState->succeeded,
                        ))
                    ;
                },
            ],
            'has pre-existing component states, no changes' => [
                'componentStateCreator' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                            ->setState($applicationStates[0]->applicationState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->applicationState->metaState->ended,
                                $applicationStates[0]->applicationState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION)
                            ->setState($applicationStates[0]->compilationState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->compilationState->metaState->ended,
                                $applicationStates[0]->compilationState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION)
                            ->setState($applicationStates[0]->executionState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->executionState->metaState->ended,
                                $applicationStates[0]->executionState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY)
                            ->setState($applicationStates[0]->eventDeliveryState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->eventDeliveryState->metaState->ended,
                                $applicationStates[0]->eventDeliveryState->metaState->succeeded,
                            ))
                    );
                },
                'retrievedApplicationState' => $applicationStates[0],
                'expectedApplicationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                        ->setState($applicationStates[0]->applicationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->applicationState->metaState->ended,
                            $applicationStates[0]->applicationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedCompilationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION)
                        ->setState($applicationStates[0]->compilationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->compilationState->metaState->ended,
                            $applicationStates[0]->compilationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedExecutionStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION)
                        ->setState($applicationStates[0]->executionState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->executionState->metaState->ended,
                            $applicationStates[0]->executionState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY)
                        ->setState($applicationStates[0]->eventDeliveryState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[0]->eventDeliveryState->metaState->ended,
                            $applicationStates[0]->eventDeliveryState->metaState->succeeded,
                        ))
                    ;
                },
            ],
            'has pre-existing component states, has changes' => [
                'componentStateCreator' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $repository
                ) use (
                    $applicationStates
                ) {
                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                            ->setState($applicationStates[0]->applicationState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->applicationState->metaState->ended,
                                $applicationStates[0]->applicationState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION)
                            ->setState($applicationStates[0]->compilationState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->compilationState->metaState->ended,
                                $applicationStates[0]->compilationState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION)
                            ->setState($applicationStates[0]->executionState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->executionState->metaState->ended,
                                $applicationStates[0]->executionState->metaState->succeeded,
                            ))
                    );

                    $repository->save(
                        new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY)
                            ->setState($applicationStates[0]->eventDeliveryState->state)
                            ->setMetaState(new MetaState(
                                $applicationStates[0]->eventDeliveryState->metaState->ended,
                                $applicationStates[0]->eventDeliveryState->metaState->succeeded,
                            ))
                    );
                },
                'retrievedApplicationState' => $applicationStates[1],
                'expectedApplicationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                        ->setState($applicationStates[1]->applicationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[1]->applicationState->metaState->ended,
                            $applicationStates[1]->applicationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedCompilationStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION)
                        ->setState($applicationStates[1]->compilationState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[1]->compilationState->metaState->ended,
                            $applicationStates[1]->compilationState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedExecutionStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION)
                        ->setState($applicationStates[1]->executionState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[1]->executionState->metaState->ended,
                            $applicationStates[1]->executionState->metaState->succeeded,
                        ))
                    ;
                },
                'expectedEventDeliveryStateCreator' => function (JobInterface $job) use ($applicationStates) {
                    return new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY)
                        ->setState($applicationStates[1]->eventDeliveryState->state)
                        ->setMetaState(new MetaState(
                            $applicationStates[1]->eventDeliveryState->metaState->ended,
                            $applicationStates[1]->eventDeliveryState->metaState->succeeded,
                        ))
                    ;
                },
            ],
        ];
    }
}
