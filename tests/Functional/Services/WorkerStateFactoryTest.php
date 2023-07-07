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
                'expectedMethod' => 'createOnWorkerStateRetrievedEvent',
            ],
        ];
    }

    public function testCreateOnWorkerStateRetrievedEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $event = new WorkerStateRetrievedEvent(md5((string) rand()), \Mockery::mock(ApplicationState::class));

        $this->workerStateFactory->createOnWorkerStateRetrievedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testCreateOnWorkerStateRetrievedEventSuccess(): void
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        self::assertSame(0, $this->workerStateRepository->count([]));

        $applicationState = new ApplicationState(
            new ComponentState(md5((string) rand()), false),
            new ComponentState(md5((string) rand()), false),
            new ComponentState(md5((string) rand()), false),
            new ComponentState(md5((string) rand()), false),
        );

        $event = new WorkerStateRetrievedEvent($job->id, $applicationState);

        $this->workerStateFactory->createOnWorkerStateRetrievedEvent($event);

        $workerStateEntity = $this->workerStateRepository->find($job->id);
        self::assertEquals(
            new WorkerState(
                $job->id,
                $applicationState->applicationState->state,
                $applicationState->compilationState->state,
                $applicationState->executionState->state,
                $applicationState->eventDeliveryState->state,
            ),
            $workerStateEntity
        );
    }
}
