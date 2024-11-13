<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestFailureType;
use App\Exception\RemoteJobActionException;
use App\Exception\RemoteRequestExceptionInterface;
use App\Message\CreateMachineMessage;
use App\MessageFailureHandler\RemoteRequestExceptionHandler;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use App\Tests\Services\Factory\JobFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;

class RemoteRequestExceptionHandlerTest extends WebTestCase
{
    use RemoteRequestFailureCreationDataProviderTrait;
    use MockeryPHPUnitIntegration;

    private RemoteRequestExceptionHandler $handler;
    private Job $job;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(RemoteRequestExceptionHandler::class);
        \assert($handler instanceof RemoteRequestExceptionHandler);
        $this->handler = $handler;

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->job = $jobFactory->createRandom();

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        $this->remoteRequestRepository = $remoteRequestRepository;

        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;

        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $remoteRequestFailureRepository->remove($entity);
        }
    }

    /**
     * @param callable(Job): RemoteRequestExceptionInterface $exceptionCreator
     */
    #[DataProvider('handleSetRemoteRequestFailureDataProvider')]
    public function testHandleSetRemoteRequestFailure(
        callable $exceptionCreator,
        RemoteRequestType $type,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $exception = $exceptionCreator($this->job);

        \assert('' !== $this->job->id);
        $remoteRequest = new RemoteRequest($this->job->id, $type, 0);
        $this->remoteRequestRepository->save($remoteRequest);

        self::assertNull($remoteRequest->getFailure());

        $envelope = new Envelope(new \stdClass());

        $this->handler->handle($envelope, $exception);

        self::assertSame(1, $this->remoteRequestFailureRepository->count([]));

        self::assertEquals(
            new RemoteRequestFailure($expectedType, $expectedCode, $expectedMessage),
            $this->remoteRequestFailureRepository->findAll()[0]
        );
    }

    /**
     * @return array<mixed>
     */
    public static function handleSetRemoteRequestFailureDataProvider(): array
    {
        $remoteRequestExceptionCases = [
            RemoteJobActionException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        \assert('' !== $job->id);

                        return new RemoteJobActionException(
                            $job,
                            $inner,
                            new CreateMachineMessage(md5((string) rand()), $job->id),
                        );
                    };
                },
                'type' => new RemoteRequestType(
                    JobComponent::MACHINE,
                    RemoteRequestAction::CREATE,
                ),
            ],
        ];

        $innerExceptionCases = self::remoteRequestFailureCreationDataProvider();

        $testCases = [];
        foreach ($remoteRequestExceptionCases as $exceptionClass => $testCaseProperties) {
            foreach ($innerExceptionCases as $innerExceptionCase) {
                $inner = $innerExceptionCase['throwable'];
                unset($innerExceptionCase['throwable']);

                $testCaseName = sprintf(
                    '%s: "%s" %d "%s"',
                    $exceptionClass,
                    $innerExceptionCase['expectedType']->value,
                    $innerExceptionCase['expectedCode'],
                    $innerExceptionCase['expectedMessage'],
                );

                $testCase = array_merge(
                    [
                        'exceptionCreator' => ($testCaseProperties['exceptionCreator'])($inner),
                        'type' => $testCaseProperties['type'],
                    ],
                    $innerExceptionCase,
                );

                $testCases[$testCaseName] = $testCase;
            }
        }

        return $testCases;
    }
}
