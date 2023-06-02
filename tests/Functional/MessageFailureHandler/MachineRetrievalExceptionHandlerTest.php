<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Exception\MachineRetrievalException;
use App\MessageFailureHandler\MachineRetrievalExceptionHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MachineRetrievalExceptionHandlerTest extends WebTestCase
{
    use RemoteRequestFailureCreationDataProviderTrait;
    use MockeryPHPUnitIntegration;

    private MachineRetrievalExceptionHandler $handler;
    private Job $job;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(MachineRetrievalExceptionHandler::class);
        \assert($handler instanceof MachineRetrievalExceptionHandler);
        $this->handler = $handler;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $this->job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $jobRepository->add($this->job);

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
     * @dataProvider remoteRequestFailureCreationDataProvider
     */
    public function testHandleSetRemoteRequestFailure(
        \Throwable $throwable,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $machine = new Machine($this->job->id, md5((string) rand()), md5((string) rand()), []);

        $remoteRequest = new RemoteRequest($machine->id, RemoteRequestType::RESULTS_CREATE, 1);
        $this->remoteRequestRepository->save($remoteRequest);

        self::assertNull($remoteRequest->getFailure());

        $this->handler->handle(new MachineRetrievalException($this->job, $machine, $throwable));

        self::assertSame(1, $this->remoteRequestFailureRepository->count([]));

        $remoteRequestFailure = $this->remoteRequestFailureRepository->findAll()[0];
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailure);

        $remoteRequestFailureData = $remoteRequestFailure->jsonSerialize();

        self::assertSame($expectedType->value, $remoteRequestFailureData['type']);
        self::assertSame($expectedCode, $remoteRequestFailureData['code']);
        self::assertSame($expectedMessage, $remoteRequestFailureData['message']);
    }
}
