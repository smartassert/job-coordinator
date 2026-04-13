<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\PreparationState;
use App\Services\JobComponentPreparationStateRetriever\JobComponentPreparationStateRetrieverInterface;

readonly class PreparationStateRetriever
{
    /**
     * @param JobComponentPreparationStateRetrieverInterface[] $retrievers
     */
    public function __construct(
        private iterable $retrievers,
    ) {}

    /**
     * @return array<PreparationState>
     */
    public function getAll(string $jobId): array
    {
        $preparationStates = [];

        foreach ($this->retrievers as $retriever) {
            $preparationStates[] = $retriever->get($jobId);
        }

        return $preparationStates;
    }
}
