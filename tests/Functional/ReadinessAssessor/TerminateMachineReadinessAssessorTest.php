<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RequestState;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\TerminateMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TerminateMachineReadinessAssessorTest extends WebTestCase
{
    private TerminateMachineReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(TerminateMachineReadinessAssessor::class);
        \assert($assessor instanceof TerminateMachineReadinessAssessor);

        $this->assessor = $assessor;
    }

    /**
     * @param callable(JobInterface, MachineRepository, ResultsJobFactory, RemoteRequestRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $setup($job, $machineRepository, $resultsJobFactory, $remoteRequestRepository);

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'machine does not exist' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'worker job preparation has failed' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    ResultsJobFactory $resultsJobFactory,
                    RemoteRequestRepository $remoteRequestRepository
                ): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            new MetaState(false, false),
                        )
                    );

                    $workerJobCreationRequest = new RemoteRequest(
                        $job->getId(),
                        RemoteRequestType::createForWorkerJobCreation()
                    );
                    $workerJobCreationRequest = $workerJobCreationRequest->setState(RequestState::FAILED);
                    $remoteRequestRepository->save($workerJobCreationRequest);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
            'results job does not exist' => [
                'setup' => function (JobInterface $job, MachineRepository $machineRepository): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            new MetaState(false, false),
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not have end state' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    ResultsJobFactory $resultsJobFactory
                ): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            new MetaState(false, false),
                        )
                    );

                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    ResultsJobFactory $resultsJobFactory
                ): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            new MetaState(false, false),
                        )
                    );

                    $resultsJobFactory->create(
                        job: $job,
                        endState: 'end-state',
                        metaState: new MetaState(true, false),
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
