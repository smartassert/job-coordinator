<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\ResultsJob as ResultsJobEntity;

/**
 * @phpstan-type SerializedResultsJob array{state: ?non-empty-string, end_state: ?non-empty-string}
 */
class ResultsJob
{
    public function __construct(
        private readonly ResultsJobEntity $entity,
    ) {
    }

    /**
     * @return array{state: ?non-empty-string, end_state: ?non-empty-string}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->entity->getState(),
            'end_state' => $this->entity->getEndState(),
        ];
    }
}
