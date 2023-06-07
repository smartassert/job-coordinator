<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestRemover;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestRemoverTest extends WebTestCase
{
    private RemoteRequestRemover $remoteRequestRemover;
    private RemoteRequestRepository $remoteRequestRepository;
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

    /**
     * @dataProvider removeForJobAndTypeNoJobDataProvider
     *
     * @param callable(): RemoteRequest[] $remoteRequestCreator
     */
    public function testRemoveForJobAndTypeNoJob(callable $remoteRequestCreator, RemoteRequestType $type): void
    {
        $remoteRequests = $remoteRequestCreator();
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $jobId = md5((string) rand());

        $this->remoteRequestRemover->removeForJobAndType($jobId, $type);

        self::assertSame(count($remoteRequests), $this->remoteRequestRepository->count([]));
    }

    /**
     * @return array<mixed>
     */
    public function removeForJobAndTypeNoJobDataProvider(): array
    {
        return [
            'no remote requests' => [
                'remoteRequestCreator' => function () {
                    return [];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
            ],
            'has matching remote requests for type' => [
                'remoteRequestCreator' => function () {
                    return [
                        new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0),
                        new RemoteRequest(md5((string) rand()), RemoteRequestType::RESULTS_CREATE, 0),
                    ];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
            ],
        ];
    }

    /**
     * @dataProvider removeForJobAndTypeDataProvider
     *
     * @param callable(string): RemoteRequest[] $remoteRequestCreator
     * @param callable(string): RemoteRequest[] $expectedRemoteRequestCreator
     */
    public function testRemoveForJobAndType(
        callable $remoteRequestCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $remoteRequests = $remoteRequestCreator($job->id);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $this->remoteRequestRemover->removeForJobAndType($job->id, $type);
        $expectedRemoteRequests = $expectedRemoteRequestCreator($job->id);

        self::assertEquals($expectedRemoteRequests, $this->remoteRequestRepository->findAll());
    }

    /**
     * @return array<mixed>
     */
    public function removeForJobAndTypeDataProvider(): array
    {
        return [
            'no remote requests' => [
                'remoteRequestCreator' => function () {
                    return [];
                },
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expectedRemoteRequestsCreator' => function () {
                    return [];
                },
            ],
            'no remote requests for type' => [
                'remoteRequestCreator' => function (string $jobId) {
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
            'single remote request for type' => [
                'remoteRequestCreator' => function (string $jobId) {
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
            'multiple remote requests for type' => [
                'remoteRequestCreator' => function (string $jobId) {
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
