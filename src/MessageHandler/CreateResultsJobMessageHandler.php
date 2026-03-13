<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateResultsJobMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ResultsClient $resultsClient,
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
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $readiness = $this->assessReadiness($message);
        if (MessageHandlingReadiness::NOW !== $readiness) {
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
            throw new RemoteJobActionException($e, $message);
        }
    }
}
