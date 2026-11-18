<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteEventName;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\MessageStateMutator;
use App\Services\NotifyUrlFactory;
use App\Services\UserSecurityTokenProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private SerializedSuiteClientInterface $serializedSuiteClient,
        private EventDispatcherInterface $eventDispatcher,
        private UserSecurityTokenProvider $userSecurityTokenProvider,
        private NotifyUrlFactory $notifyUrlFactory,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $jobId = $message->getJobId();

        $readiness = $this->readinessAssessor->isReady($jobId);
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $notifyUrl = $this->notifyUrlFactory->create(RemoteEventName::SERIALIZED_SUITE_STATE_CHANGED->value);
        $userSecurityToken = $this->userSecurityTokenProvider->get($jobId);
        if (null === $notifyUrl || null === $userSecurityToken) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $userSecurityToken,
                $jobId,
                $message->suiteId,
                $notifyUrl,
                $message->parameters,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteCreatedEvent(
                $jobId,
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
