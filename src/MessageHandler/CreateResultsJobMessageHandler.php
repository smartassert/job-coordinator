<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateResultsJobMessageHandler
{
    public function __construct(
        private readonly ResultsClient $resultsClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(CreateResultsJobMessage $message): void
    {
        if ($this->resultsJobRepository->has($message->getJobId())) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $message->getJobId());
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
