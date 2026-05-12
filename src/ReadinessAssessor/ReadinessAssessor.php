<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;

readonly class ReadinessAssessor implements ReadinessAssessorInterface
{
    /**
     * @var ReadinessHandlerInterface[]
     */
    private array $assessors;

    /**
     * @param iterable<mixed> $assessors
     */
    public function __construct(iterable $assessors)
    {
        $filteredAssessors = [];

        foreach ($assessors as $assessor) {
            if ($assessor instanceof ReadinessHandlerInterface) {
                $filteredAssessors[] = $assessor;
            }
        }

        $this->assessors = $filteredAssessors;
    }

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        foreach ($this->assessors as $assessor) {
            $readiness = $assessor->isReady($message);
            if (null !== $readiness) {
                return $readiness;
            }
        }

        return MessageHandlingReadiness::NEVER;
    }
}
