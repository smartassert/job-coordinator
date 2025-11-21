<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\FooReadinessAssessorInterface;
use App\Repository\ResultsJobRepository;
use App\Services\SerializedSuiteStore;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateWorkerJobMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private SerializedSuiteStore $serializedSuiteStore,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteClient $serializedSuiteClient,
        private WorkerClientFactory $workerClientFactory,
        EventDispatcherInterface $eventDispatcher,
        FooReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor);
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerNotReadyException
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $this->assessReadiness($message);

        $serializedSuiteModel = $this->serializedSuiteStore->retrieve($message->getJobId());
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if (null === $serializedSuiteModel || null === $resultsJob) {
            throw new MessageHandlerNotReadyException($message, MessageHandlingReadiness::EVENTUALLY);
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
