<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateResultsJobMessageHandler
{
    /**
     * @param non-empty-string $notifyUrl
     */
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private ResultsClient $resultsClient,
        private EventDispatcherInterface $eventDispatcher,
        private AuthenticationTokenProvider $authenticationTokenProvider,
        private string $notifyUrl,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob(
                $authenticationToken,
                $message->getJobId(),
                $this->notifyUrl,
            );

            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->getJobId(),
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
