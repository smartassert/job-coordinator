<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteMessage;
use App\Model\SerializedSuiteEndStates;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws SerializedSuiteRetrievalException
     */
    public function __invoke(GetSerializedSuiteMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuiteEntity) {
            return;
        }

        if (in_array($serializedSuiteEntity->getState(), SerializedSuiteEndStates::END_STATES)) {
            return;
        }

        try {
            $serializedSuiteModel = $this->serializedSuiteClient->get(
                $message->authenticationToken,
                $message->serializedSuiteId,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteRetrievedEvent(
                $message->authenticationToken,
                $job->id,
                $serializedSuiteModel
            ));
        } catch (\Throwable $e) {
            throw new SerializedSuiteRetrievalException($job, $serializedSuiteEntity, $e, $message);
        }
    }
}
