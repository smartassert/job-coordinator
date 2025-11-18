<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Model\JobInterface;
use App\Repository\SerializedSuiteRepository;
use App\Services\SerializedSuiteMutator;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\SourcesClientSerializedSuiteFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\SourcesClient\Model\SerializedSuite as SourcesSerializedSuite;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class SerializedSuiteMutatorTest extends WebTestCase
{
    private SerializedSuiteRepository $serializedSuiteRepository;
    private SerializedSuiteMutator $serializedSuiteMutator;
    private JobFactory $jobFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->jobFactory = $jobFactory;

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

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->serializedSuiteMutator::getSubscribedEvents();
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
            SerializedSuiteRetrievedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteRetrievedEvent::class,
                'expectedMethod' => 'setState',
            ],
        ];
    }

    /**
     * @param callable(JobFactory): ?JobInterface                      $jobCreator
     * @param callable(?JobInterface, SerializedSuiteRepository): void $serializedSuiteCreator
     * @param callable(?JobInterface): SerializedSuiteRetrievedEvent   $eventCreator
     * @param callable(?JobInterface): ?SerializedSuite                $expectedSerializedSuiteCreator
     */
    #[DataProvider('setStateSuccessDataProvider')]
    public function testSetStateSuccess(
        callable $jobCreator,
        callable $serializedSuiteCreator,
        callable $eventCreator,
        callable $expectedSerializedSuiteCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);
        $serializedSuiteCreator($job, $this->serializedSuiteRepository);

        $event = $eventCreator($job);

        $this->serializedSuiteMutator->setState($event);

        $resultsJob = null === $job
            ? null
            : $this->serializedSuiteRepository->find($job->getId());

        self::assertEquals($expectedSerializedSuiteCreator($job), $resultsJob);
    }

    /**
     * @return array<mixed>
     */
    public static function setStateSuccessDataProvider(): array
    {
        $serializedSuiteId = md5((string) rand());
        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {},
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    $serializedSuiteId = (string) new Ulid();
                    $suiteId = (string) new Ulid();

                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $jobId,
                        SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $suiteId)
                    );
                },
                'expectedSerializedSuiteCreator' => function () {
                    return null;
                },
            ],
            'no serialized suite' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function () {},
                'eventCreator' => function (JobInterface $job) {
                    $serializedSuiteId = (string) new Ulid();

                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $job->getSuiteId())
                    );
                },
                'expectedSerializedSuiteCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) use ($serializedSuiteId) {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'requested',
                        false,
                        false
                    );
                    $serializedSuiteRepository->save($serializedSuite);
                },
                'eventCreator' => function (JobInterface $job) use ($serializedSuiteId) {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        new SourcesSerializedSuite(
                            $serializedSuiteId,
                            'suite id',
                            [],
                            'requested',
                            false,
                            false,
                            null,
                            null,
                        ),
                    );
                },
                'expectedSerializedSuiteCreator' => function (JobInterface $job) use ($serializedSuiteId) {
                    return new SerializedSuite($job->getId(), $serializedSuiteId, 'requested', false, false);
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'serializedSuiteCreator' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) use ($serializedSuiteId) {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'requested',
                        false,
                        false
                    );
                    $serializedSuiteRepository->save($serializedSuite);
                },
                'eventCreator' => function (JobInterface $job) use ($serializedSuiteId) {
                    return new SerializedSuiteRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        new SourcesSerializedSuite(
                            $serializedSuiteId,
                            'suite id',
                            [],
                            'prepared',
                            true,
                            true,
                            null,
                            null,
                        ),
                    );
                },
                'expectedSerializedSuiteCreator' => function (JobInterface $job) use ($serializedSuiteId) {
                    return new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true);
                },
            ],
        ];
    }
}
