<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\SerializedSuiteRepository;

readonly class GetSerializedSuiteReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::SERIALIZED_SUITE !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::RETRIEVE !== $requestType->action) {
            return null;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($message->getJobId());
        if (null === $serializedSuite || $serializedSuite->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
