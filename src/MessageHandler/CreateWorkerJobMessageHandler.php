<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\WorkerJobCreationStage;
use App\Event\CreateWorkerJobFailedEvent;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Exception\UnrecoverableRemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Services\UnhandleableMessageHandler;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateWorkerJobMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private UnhandleableMessageHandler $unhandleableMessageHandler,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteClientInterface $serializedSuiteClient,
        private WorkerClientFactory $workerClientFactory,
        private EventDispatcherInterface $eventDispatcher,
        private AuthenticationTokenProvider $authenticationTokenProvider,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->findByJobId($message->getJobId());
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if (null === $serializedSuiteEntity || null === $resultsJob) {
            $this->unhandleableMessageHandler->handle($message, MessageHandlingReadiness::EVENTUALLY);

            return;
        }

        $workerClient = $this->workerClientFactory->create($message->machineIpAddress);

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->read($authenticationToken, $serializedSuiteEntity->id);
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CreateWorkerJobFailedEvent(
                $message->getJobId(),
                WorkerJobCreationStage::SERIALIZED_SUITE_READ,
                $e
            ));

            throw new UnrecoverableRemoteJobActionException($e, $message);
        }

        try {
            $workerJob = $workerClient->createJob(
                $message->getJobId(),
                $resultsJob->eventAddUrl,
                $message->maximumDurationInSeconds,
                $serializedSuite
            );
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CreateWorkerJobFailedEvent(
                $message->getJobId(),
                WorkerJobCreationStage::WORKER_JOB_CREATE,
                $e
            ));

            throw new UnrecoverableRemoteJobActionException($e, $message);
        }

        $this->eventDispatcher->dispatch(new CreateWorkerJobRequestedEvent(
            $message->getJobId(),
            $message->machineIpAddress,
            $workerJob,
        ));
    }
}
