<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;

readonly class ReadinessAssessor implements FooReadinessAssessorInterface
{
    /**
     * @var ReadinessAssessorInterface[]
     */
    private array $assessors;

    /**
     * @param iterable<mixed> $assessors
     */
    public function __construct(iterable $assessors)
    {
        $filteredAssessors = [];

        foreach ($assessors as $assessor) {
            if ($assessor instanceof ReadinessAssessorInterface) {
                $filteredAssessors[] = $assessor;
            }
        }

        $this->assessors = $filteredAssessors;
    }

    public function isReady(RemoteRequestType $type, string $jobId): MessageHandlingReadiness
    {
        foreach ($this->assessors as $assessor) {
            if ($assessor->handles($type)) {
                return $assessor->isReady($jobId);
            }
        }

        return MessageHandlingReadiness::NEVER;
    }
}
