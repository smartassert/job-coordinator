<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\ReadinessAssessor\CreateSerializedSuiteReadinessAssessor;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateSerializedSuiteReadinessAssessorTest extends WebTestCase
{
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

        $assessor = self::getContainer()->get(CreateSerializedSuiteReadinessAssessor::class);
        \assert($assessor instanceof CreateSerializedSuiteReadinessAssessor);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'serialized suite already exists' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteRepository->save(
                        new SerializedSuite(
                            $job->getId(),
                            'serialized suite id',
                            'state',
                            false,
                            false
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
