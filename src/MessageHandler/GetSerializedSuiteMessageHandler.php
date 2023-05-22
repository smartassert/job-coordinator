<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class GetSerializedSuiteMessageHandler
{
    private const SERIALIZED_SUITE_END_STATES = ['prepared', 'failed'];

    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws SerializedSuiteRetrievalException
     */
    public function __invoke(GetSerializedSuiteMessage $message): void
    {
        $job = $this->jobRepository->findOneBy(['serializedSuiteId' => $message->serializedSuiteId]);
        if (null === $job) {
            return;
        }

        if (in_array($job->getSerializedSuiteState(), self::SERIALIZED_SUITE_END_STATES)) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->get(
                $message->authenticationToken,
                $message->serializedSuiteId,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteRetrievedEvent(
                $message->authenticationToken,
                $job->id,
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new SerializedSuiteRetrievalException($job, $e);
        }

        $serializedSuiteState = $serializedSuite->getState();
        if (!in_array($serializedSuiteState, self::SERIALIZED_SUITE_END_STATES)) {
            $this->messageBus->dispatch($message);
        }
    }
}
