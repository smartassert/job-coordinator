<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class GetResultsJobStateMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private ResultsClient $resultsClient,
        EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $messageBus, $logger);
    }

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetResultsJobStateMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message);
        $this->setMessageState($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

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
