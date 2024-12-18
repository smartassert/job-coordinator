<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetResultsJobStateMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ResultsClient $resultsClient,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor);
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerNotReadyException
     */
    public function __invoke(GetResultsJobStateMessage $message): void
    {
        $this->assessReadiness($message);

        try {
            $resultsJobState = $this->resultsClient->getJobStatus($message->authenticationToken, $message->getJobId());
            $this->eventDispatcher->dispatch(new ResultsJobStateRetrievedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $resultsJobState
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
