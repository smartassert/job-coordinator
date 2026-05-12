<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Message\GetSerializedSuiteMessage;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetSerializedSuiteReadinessHandler;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class GetSerializedSuiteReadinessAssessorTest extends WebTestCase
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

        $assessor = self::getContainer()->get(GetSerializedSuiteReadinessHandler::class);
        \assert($assessor instanceof GetSerializedSuiteReadinessHandler);

        $message = new GetSerializedSuiteMessage(
            'authentication-token',
            $job->getId(),
            (string) new Ulid(),
            (string) new Ulid(),
        );

        self::assertSame($expected, $assessor->isReady($message));
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
                        new MetaState(true, true),
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
                        new MetaState(false, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
