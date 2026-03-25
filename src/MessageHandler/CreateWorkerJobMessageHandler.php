<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobFailedEvent;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Exception\UnrecoverableRemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateWorkerJobMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteClient $serializedSuiteClient,
        private WorkerClientFactory $workerClientFactory,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor, $messageBus, $logger);
    }

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $readiness = $this->assessReadiness($message);
        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->get($message->getJobId());
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if (null === $serializedSuiteEntity || null === $resultsJob) {
            $this->handleNonHandleableMessage($message, MessageHandlingReadiness::EVENTUALLY);

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read(
                $message->authenticationToken,
                $serializedSuiteEntity->id
            );
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CreateWorkerJobFailedEvent($message->getJobId(), $e));

            throw new UnrecoverableRemoteJobActionException($e, $message);
        }

        try {
            $workerJob = $workerClient->createJob(
                $message->getJobId(),
                $resultsJob->token,
                $message->maximumDurationInSeconds,
                $serializedSuite
            );
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new CreateWorkerJobFailedEvent($message->getJobId(), $e));

            throw new UnrecoverableRemoteJobActionException($e, $message);
        }

        $this->eventDispatcher->dispatch(new CreateWorkerJobRequestedEvent(
            $message->getJobId(),
            $message->machineIpAddress,
            $workerJob,
        ));
    }
}
