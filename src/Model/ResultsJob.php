<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedResultsJob array{
 *   state: ?non-empty-string,
 *   end_state: ?non-empty-string
 * }
 */
class ResultsJob
{
    public function __construct(
        private readonly ?ResultsJobInterface $entity,
    ) {
    }

    /**
     * @return SerializedResultsJob
     */
    public function toArray(): array
    {
        return [
            'state' => $this->entity?->getState() ?? null,
            'end_state' => $this->entity?->getEndState() ?? null,
        ];
    }
}
