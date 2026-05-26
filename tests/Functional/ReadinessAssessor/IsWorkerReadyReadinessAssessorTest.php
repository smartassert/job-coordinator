<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\IsWorkerReadyReadinessAssessor;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class IsWorkerReadyReadinessAssessorTest extends WebTestCase
{
    private IsWorkerReadyReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(IsWorkerReadyReadinessAssessor::class);
        \assert($assessor instanceof IsWorkerReadyReadinessAssessor);

        $this->assessor = $assessor;
    }

    /**
     * @param callable(JobInterface, MachineRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $setup($job, $machineRepository);

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'no machine' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'machine is already ready' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository
                ): void {
                    $machine = new Machine($job->getId(), 'find/finding', 'pre_active');
                    $machine = $machine->setIsReady();
                    $machineRepository->save($machine);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'machine has ended' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository
                ): void {
                    $machine = new Machine($job->getId(), 'find/finding', 'pre_active');
                    $machine = $machine->setMetaState(new MetaState(true, true, false));
                    $machineRepository->save($machine);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'machine is not already ready' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository
                ): void {
                    $machine = new Machine($job->getId(), 'find/finding', 'pre_active');
                    $machineRepository->save($machine);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
