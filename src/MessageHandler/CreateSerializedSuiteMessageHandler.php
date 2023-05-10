<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\RequestState;
use App\Exception\SerializedSuiteCreationException;
use App\Message\CreateSerializedSuiteMessage;
use App\Message\GetSerializedSuiteStateMessage;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws SerializedSuiteCreationException
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if (null === $job) {
            return;
        }

        $job->setSerializedSuiteRequestState(RequestState::REQUESTING);

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $message->authenticationToken,
                $job->suiteId,
                $message->parameters,
            );

            $job = $job->setSerializedSuiteRequestState(null);
            $job->setSerializedSuiteId($serializedSuite->getId());

            $this->jobRepository->add($job);
            $this->messageBus->dispatch(new Envelope(
                new GetSerializedSuiteStateMessage($message->authenticationToken, $serializedSuite->getId()),
                [new NonDelayedStamp()]
            ));
        } catch (\Throwable $e) {
            $job->setSerializedSuiteRequestState(RequestState::HALTED);

            throw new SerializedSuiteCreationException($job, $e);
        }
    }
}
