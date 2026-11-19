<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteEventType;
use App\Enum\WorkerJobCreationStage;
use App\Event\CreateWorkerJobFailedEvent;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Exception\UnrecoverableRemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\MessageStateMutator;
use App\Services\NotifyUrlFactory;
use App\Services\UnhandleableMessageHandler;
use App\Services\UserSecurityTokenProvider;
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
        private UserSecurityTokenProvider $userSecurityTokenProvider,
        private JobRepository $jobRepository,
        private NotifyUrlFactory $notifyUrlFactory,
        private MachineRepository $machineRepository,
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

        $job = $this->jobRepository->find($message->getJobId());
        $serializedSuiteEntity = $this->serializedSuiteRepository->findByJobId($message->getJobId());
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        $userSecurityToken = $this->userSecurityTokenProvider->get($message->getJobId());
        $machine = $this->machineRepository->find($message->getJobId());
        $ipAddress = $machine?->getIpAddress();

        if (
            null === $job
            || null === $serializedSuiteEntity
            || null === $resultsJob
            || null === $userSecurityToken
            || null === $machine
            || null === $ipAddress
        ) {
            $this->unhandleableMessageHandler->handle($message, MessageHandlingReadiness::EVENTUALLY);

            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->read($userSecurityToken, $serializedSuiteEntity->id);
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CreateWorkerJobFailedEvent(
                $message->getJobId(),
                WorkerJobCreationStage::SERIALIZED_SUITE_READ,
                $e
            ));

            throw new UnrecoverableRemoteJobActionException($e, $message);
        }

        $workerClient = $this->workerClientFactory->create($ipAddress);

        try {
            $workerJob = $workerClient->createJob(
                $message->getJobId(),
                $resultsJob->eventNotifyUrl,
                $resultsJob->eventNotifyToken,
                $this->notifyUrlFactory->create(RemoteEventType::MACHINE_STATE_CHANGED),
                $job->getNotifyToken(),
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
            $workerJob,
        ));
    }
}
