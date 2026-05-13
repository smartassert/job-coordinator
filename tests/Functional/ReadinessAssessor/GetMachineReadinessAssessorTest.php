<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Message\GetMachineMessage;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerClientMachine;
use SmartAssert\WorkerManagerClient\Model\MetaState as WorkerManagerClientMetaState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetMachineReadinessAssessorTest extends WebTestCase
{
    private GetMachineReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($assessor instanceof GetMachineReadinessAssessor);

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

        $message = new GetMachineMessage(
            'authentication-token',
            $job->getId(),
            new WorkerManagerClientMachine(
                'machine-id',
                'state',
                'state-category',
                ['127.0.0.1'],
                null,
                false,
                false,
                false,
                false,
                new WorkerManagerClientMetaState(false, false)
            ),
        );

        self::assertSame($expected, $this->assessor->isReady($message));
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
            'machine has end state' => [
                'setup' => function (JobInterface $job, MachineRepository $machineRepository): void {
                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            new MetaState(true, false),
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
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
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
