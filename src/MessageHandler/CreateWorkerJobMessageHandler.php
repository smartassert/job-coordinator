<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\Repository\ResultsJobRepository;
use App\Services\SerializedSuiteStore;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateWorkerJobMessageHandler
{
    public function __construct(
        private readonly SerializedSuiteStore $serializedSuiteStore,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly WorkerClientFactory $workerClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $serializedSuiteModel = $this->serializedSuiteStore->retrieve($message->getJobId());
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if (
            null === $serializedSuiteModel
            || $serializedSuiteModel->isPreparing()
            || null === $resultsJob
        ) {
            $this->eventDispatcher->dispatch(new MessageNotYetHandleableEvent($message));

            return;
        }

        if ($serializedSuiteModel->hasFailed()) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read(
                $message->authenticationToken,
                $serializedSuiteModel->getId()
            );

            $workerJob = $workerClient->createJob(
                $message->getJobId(),
                $resultsJob->token,
                $message->maximumDurationInSeconds,
                $serializedSuite
            );

            $this->eventDispatcher->dispatch(new CreateWorkerJobRequestedEvent(
                $message->getJobId(),
                $message->machineIpAddress,
                $workerJob,
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
