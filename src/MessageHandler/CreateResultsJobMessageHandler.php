<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteEventName;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\MessageStateMutator;
use App\Services\NotifyUrlFactory;
use App\Services\UserSecurityTokenProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateResultsJobMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private ResultsClient $resultsClient,
        private EventDispatcherInterface $eventDispatcher,
        private UserSecurityTokenProvider $userSecurityTokenProvider,
        private NotifyUrlFactory $notifyUrlFactory,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $jobId = $message->getJobId();

        $readiness = $this->readinessAssessor->isReady($jobId);
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $notifyUrl = $this->notifyUrlFactory->create(RemoteEventName::RESULTS_JOB_STATE_CHANGED->value);
        $userSecurityToken = $this->userSecurityTokenProvider->get($jobId);
        if (null === $notifyUrl || null === $userSecurityToken) {
            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob($userSecurityToken, $jobId, $notifyUrl);

            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent($jobId, $resultsJob));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
