<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteFactory;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(self::NAME)]
final readonly class SourcesSerializedSuiteStateChangedWebhookConsumer implements ConsumerInterface
{
    public const string NAME = 'sources.serialized_suite.state_changed';

    public function __construct(
        private SerializedSuiteFactory $serializedSuiteFactory,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (self::NAME !== $event->getName()) {
            return;
        }

        $eventSerializedSuite = $this->serializedSuiteFactory->create($event->getPayload());
        if (null === $eventSerializedSuite) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->findBySerializedSuiteId(
            $eventSerializedSuite->getId(),
        );

        if (null === $serializedSuiteEntity) {
            return;
        }

        $jobId = $serializedSuiteEntity->getJobId();
        if ('' === $jobId) {
            return;
        }

        $this->eventDispatcher->dispatch(new SerializedSuiteRetrievedEvent($jobId, $eventSerializedSuite));
    }
}
