<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
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
                            false,
                            true,
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
                            false,
                            false,
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
