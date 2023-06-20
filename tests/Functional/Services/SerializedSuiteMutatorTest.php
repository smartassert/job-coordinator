<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\SerializedSuiteMutator;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite as SourcesSerializedSuite;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteMutatorTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private SerializedSuiteRepository $serializedSuiteRepository;
    private SerializedSuiteMutator $serializedSuiteMutator;

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

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        foreach ($serializedSuiteRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->serializedSuiteRepository = $serializedSuiteRepository;

        $serializedSuiteMutator = self::getContainer()->get(SerializedSuiteMutator::class);
        \assert($serializedSuiteMutator instanceof SerializedSuiteMutator);
        $this->serializedSuiteMutator = $serializedSuiteMutator;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->serializedSuiteMutator);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->serializedSuiteMutator::getSubscribedEvents();
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
            SerializedSuiteRetrievedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteRetrievedEvent::class,
                'expectedMethod' => 'setState',
            ],
        ];
    }

    /**
     * @dataProvider setStateSuccessDataProvider
     *
     * @param callable(non-empty-string, JobRepository): ?Job $jobCreator
     * @param callable(?Job, SerializedSuiteRepository): void $serializedSuiteCreator
     * @param callable(?Job): SerializedSuiteRetrievedEvent   $eventCreator
     * @param callable(?Job): ?SerializedSuite                $expectedSerializedSuiteCreator
     */
    public function testSetStateSuccess(
        callable $jobCreator,
        callable $serializedSuiteCreator,
        callable $eventCreator,
        callable $expectedSerializedSuiteCreator,
    ): void {
        $jobId = md5((string) rand());

        $job = $jobCreator($jobId, $this->jobRepository);
        $serializedSuiteCreator($job, $this->serializedSuiteRepository);

        $event = $eventCreator($job);

        $this->serializedSuiteMutator->setState($event);

        $resultsJob = $this->serializedSuiteRepository->find($jobId);
        self::assertEquals($expectedSerializedSuiteCreator($job), $resultsJob);
    }

    /**
     * @return array<mixed>
     */
    public function setStateSuccessDataProvider(): array
    {
        $serializedSuiteId = md5((string) rand());
        $jobCreator = function (string $jobId, JobRepository $jobRepository) {
            \assert('' !== $jobId);

            $job = new Job($jobId, 'user id', 'suite id', 600);
            $jobRepository->add($job);

            return $job;
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {
                },
                'eventCreator' => function () {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        md5((string) rand()),
                        \Mockery::mock(SourcesSerializedSuite::class)
                    );
                },
                'expectedSerializedSuiteCreator' => function () {
                    return null;
                },
            ],
            'no serialized suite' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function () {
                },
                'eventCreator' => function (Job $job) {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        \Mockery::mock(SourcesSerializedSuite::class)
                    );
                },
                'expectedSerializedSuiteCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function (
                    Job $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) use ($serializedSuiteId) {
                    $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, 'requested');
                    $serializedSuiteRepository->save($serializedSuite);
                },
                'eventCreator' => function (Job $job) use ($serializedSuiteId) {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new SourcesSerializedSuite($serializedSuiteId, 'suite id', [], 'requested', null, null),
                    );
                },
                'expectedSerializedSuiteCreator' => function (Job $job) use ($serializedSuiteId) {
                    return new SerializedSuite($job->id, $serializedSuiteId, 'requested');
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function (
                    Job $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) use ($serializedSuiteId) {
                    $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, 'requested');
                    $serializedSuiteRepository->save($serializedSuite);
                },
                'eventCreator' => function (Job $job) use ($serializedSuiteId) {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new SourcesSerializedSuite($serializedSuiteId, 'suite id', [], 'prepared', null, null),
                    );
                },
                'expectedSerializedSuiteCreator' => function (Job $job) use ($serializedSuiteId) {
                    return new SerializedSuite($job->id, $serializedSuiteId, 'prepared');
                },
            ],
        ];
    }
}
