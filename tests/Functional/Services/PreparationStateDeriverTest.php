<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\PreparationStateDeriver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PreparationStateDeriverTest extends WebTestCase
{
    private PreparationStateDeriver $preparationStateDeriver;
    private RemoteRequestRepository $remoteRequestRepository;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $preparationStateDeriver = self::getContainer()->get(PreparationStateDeriver::class);
        \assert($preparationStateDeriver instanceof PreparationStateDeriver);
        $this->preparationStateDeriver = $preparationStateDeriver;

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
     * @dataProvider getForResultsJobDataProvider
     *
     * @param callable(Job, ResultsJobRepository): void    $entityCreator
     * @param callable(Job, RemoteRequestRepository): void $remoteRequestsCreator
     */
    public function testGetForResultsJob(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        PreparationState $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $entityCreator($job, $resultsJobRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertSame($expected, $this->preparationStateDeriver->getForResultsJob($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForResultsJobDataProvider(): array
    {
        return [
            'no entity, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => PreparationState::PENDING,
            ],
            'no entity, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "failed"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => PreparationState::FAILED,
            ],
            'no entity, request of state "failed", request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            1
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'has entity, no remote requests' => [
                'entityCreator' => function (Job $job, ResultsJobRepository $repository) {
                    $repository->save(new ResultsJob(
                        $job->id,
                        'results job token',
                        'awaiting-events',
                        null
                    ));
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => PreparationState::SUCCEEDED,
            ],
            'has entity, many remote requests' => [
                'entityCreator' => function (Job $job, ResultsJobRepository $repository) {
                    $repository->save(new ResultsJob(
                        $job->id,
                        'results job token',
                        'awaiting-events',
                        null
                    ));
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => PreparationState::SUCCEEDED,
            ],
        ];
    }

    /**
     * @dataProvider getForSerializedSuiteDataProvider
     *
     * @param callable(Job, SerializedSuiteRepository): void $entityCreator
     * @param callable(Job, RemoteRequestRepository): void   $remoteRequestsCreator
     */
    public function testGetForSerializedSuite(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        PreparationState $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $entityCreator($job, $serializedSuiteRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertSame($expected, $this->preparationStateDeriver->getForSerializedSuite($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForSerializedSuiteDataProvider(): array
    {
        return [
            'no entity, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => PreparationState::PENDING,
            ],
            'no entity, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'no entity, single request of state "failed"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => PreparationState::FAILED,
            ],
            'no entity, request of state "failed", request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            1
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => PreparationState::PREPARING,
            ],
            'has entity, no remote requests' => [
                'entityCreator' => function (Job $job, SerializedSuiteRepository $repository) {
                    $repository->save(new SerializedSuite($job->id, md5((string) rand()), 'requested'));
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => PreparationState::SUCCEEDED,
            ],
            'has entity, many remote requests' => [
                'entityCreator' => function (Job $job, SerializedSuiteRepository $repository) {
                    $repository->save(new SerializedSuite($job->id, md5((string) rand()), 'requested'));
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => PreparationState::SUCCEEDED,
            ],
        ];
    }
}
