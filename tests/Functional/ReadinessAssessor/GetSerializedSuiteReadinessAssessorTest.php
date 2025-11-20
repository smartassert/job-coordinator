<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\GetSerializedSuiteReadinessAssessor;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetSerializedSuiteReadinessAssessorTest extends WebTestCase
{
    private GetSerializedSuiteReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(GetSerializedSuiteReadinessAssessor::class);
        \assert($assessor instanceof GetSerializedSuiteReadinessAssessor);

        $this->assessor = $assessor;
    }

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
    }

    /**
     * @param callable(JobInterface, SerializedSuiteRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $setup($job, $serializedSuiteRepository);

        $assessor = self::getContainer()->get(GetSerializedSuiteReadinessAssessor::class);
        \assert($assessor instanceof GetSerializedSuiteReadinessAssessor);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'serialized suite does not exist' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'serialized suite has end state' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        md5((string) rand()),
                        'prepared',
                        true,
                        true,
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        md5((string) rand()),
                        'preparing',
                        false,
                        false,
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
