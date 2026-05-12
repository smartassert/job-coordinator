<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;
use App\Repository\SerializedSuiteRepository;

readonly class CreateSerializedSuiteReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        if (!RemoteRequestType::createForSerializedSuiteCreation()->equals($message->getRemoteRequestType())) {
            return null;
        }

        if ($this->serializedSuiteRepository->has($message->getJobId())) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
