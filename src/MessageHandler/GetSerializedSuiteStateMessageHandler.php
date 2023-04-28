<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteStateMessage;
use App\MessageDispatcher\SerializedSuiteStateChangeCheckMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSerializedSuiteStateMessageHandler
{
    private const SERIALIZED_SUITE_END_STATES = ['prepared', 'failed'];

    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly SerializedSuiteStateChangeCheckMessageDispatcher $messageDispatcher,
    ) {
    }

    /**
     * @throws SerializedSuiteRetrievalException
     */
    public function __invoke(GetSerializedSuiteStateMessage $message): void
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
        } catch (\Throwable $e) {
            throw new SerializedSuiteRetrievalException($message->serializedSuiteId, $e);
        }

        $serializedSuiteState = $serializedSuite->getState();
        if ('' !== $serializedSuiteState && $serializedSuiteState !== $job->getSerializedSuiteState()) {
            $job->setSerializedSuiteState($serializedSuiteState);
            $this->jobRepository->add($job);
        }

        if (!in_array($job->getSerializedSuiteState(), self::SERIALIZED_SUITE_END_STATES)) {
            $this->messageDispatcher->dispatch($message);
        }
    }
}
