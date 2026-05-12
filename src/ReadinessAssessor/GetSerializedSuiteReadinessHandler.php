<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;
use App\Repository\SerializedSuiteRepository;

readonly class GetSerializedSuiteReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        if (!RemoteRequestType::createForSerializedSuiteRetrieval()->equals($message->getRemoteRequestType())) {
            return null;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($message->getJobId());
        if (null === $serializedSuite || $serializedSuite->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
