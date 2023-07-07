<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerState;
use App\Event\WorkerStateRetrievedEvent;
use App\Repository\JobRepository;
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

        $event = new WorkerStateRetrievedEvent(md5((string) rand()), \Mockery::mock(ApplicationState::class));

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

        $event = new WorkerStateRetrievedEvent($job->id, $retrievedApplicationState);
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
}
