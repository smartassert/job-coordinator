<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\SerializedSuiteCreationException;
use App\Message\CreateSerializedSuiteMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws SerializedSuiteCreationException
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $message->authenticationToken,
                $job->id,
                $job->suiteId,
                $message->parameters,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteCreatedEvent(
                $message->authenticationToken,
                $job->id,
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new SerializedSuiteCreationException($job, $e);
        }
    }
}
