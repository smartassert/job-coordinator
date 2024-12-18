<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\ResultsJobCreatedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateResultsJobMessageHandler extends AbstractMessageHandler
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
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $this->isReady($message);

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $message->getJobId());
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
