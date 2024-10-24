<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Repository\WorkerComponentStateRepository;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerComponentStateRepositoryTest extends WebTestCase
{
    private WorkerComponentStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $repository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($repository instanceof WorkerComponentStateRepository);
        foreach ($repository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->repository = $repository;
    }

    /**
     * @param callable(Job, WorkerComponentStateRepository): void $statesCreator
     * @param callable(Job): WorkerComponentState[]               $expectedStatesCreator
     */
    #[DataProvider('getAllForJobDataProvider')]
    public function testGetAllForJob(callable $statesCreator, callable $expectedStatesCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $statesCreator($job, $this->repository);

        self::assertEquals(
            $this->repository->findBy(['jobId' => $job->id]),
            $expectedStatesCreator($job)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function getAllForJobDataProvider(): array
    {
        return [
            'no states' => [
                'statesCreator' => function () {
                },
                'expectedStatesCreator' => function () {
                    return [];
                },
            ],
            'no matching states' => [
                'statesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::APPLICATION))
                            ->setState('compiling')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::COMPILATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::EXECUTION))
                            ->setState('pending')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedStatesCreator' => function () {
                    return [];
                },
            ],
            'single matching state' => [
                'statesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('executing')
                            ->setIsEndState(false)
                    );
                },
                'expectedStatesCreator' => function (Job $job) {
                    return [
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('executing')
                            ->setIsEndState(false),
                    ];
                },
            ],
            'multiple matching state' => [
                'statesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('executing')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::EXECUTION))
                            ->setState('pending')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(md5((string) rand()), WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedStatesCreator' => function (Job $job) {
                    return [
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('executing')
                            ->setIsEndState(false),
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true),
                    ];
                },
            ],
        ];
    }
}
