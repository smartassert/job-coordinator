<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\TerminateMachineReadinessHandler;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TerminateMachineReadinessAssessorTest extends WebTestCase
{
    private TerminateMachineReadinessHandler $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(TerminateMachineReadinessHandler::class);
        \assert($assessor instanceof TerminateMachineReadinessHandler);

        $this->assessor = $assessor;
    }

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForMachineTermination()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
    }

    /**
     * @param callable(JobInterface, MachineRepository, ResultsJobRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $setup($job, $machineRepository, $resultsJobRepository);

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
            'results job does not exist' => [
                'setup' => function (JobInterface $job, MachineRepository $machineRepository): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            false,
                            false,
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not have end state' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    ResultsJobRepository $resultsJobRepository
                ): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            false,
                            false,
                        )
                    );

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'state', null)
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    ResultsJobRepository $resultsJobRepository
                ): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            false,
                            false,
                        )
                    );

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'state', 'end-state')
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
