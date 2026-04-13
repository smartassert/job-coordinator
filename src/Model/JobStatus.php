<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 */
readonly class JobStatus implements \JsonSerializable
{
    public function __construct(
        private JobInterface $job,
        private MetaState $metaState,
        private PreparationState $preparationState,
        private JobComponents $components,
    ) {}

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->job->toArray(),
            [
                'meta_state' => $this->metaState,
                'preparation' => $this->preparationState,
                'components' => $this->components,
            ]
        );
    }
}
