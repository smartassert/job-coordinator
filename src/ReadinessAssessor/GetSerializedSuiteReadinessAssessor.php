<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\SerializedSuiteRepository;

readonly class GetSerializedSuiteReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        $serializedSuite = $this->serializedSuiteRepository->find($message->getJobId());
        if (null === $serializedSuite || $serializedSuite->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
