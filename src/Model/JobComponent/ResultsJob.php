<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Enum\JobComponentName;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 *
 * @phpstan-type SerializedResultsJob array{
 *   state: ?string,
 *   end_state: ?string,
 *   meta_state: MetaState,
 *   requests: SerializedRemoteRequestCollection,
 *   preparation: array{}
 * }
 */
readonly class ResultsJob implements SerializeToArrayInterface, NamedJobComponentInterface
{
    public function __construct(
        private ?ResultsJobEntity $entity,
        private RemoteRequestCollection $requests,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->entity && $this->requests->isEmpty();
    }

    public function getName(): JobComponentName
    {
        return JobComponentName::RESULTS_JOB;
    }

    public function getMetaState(): MetaState
    {
        return $this->entity?->getMetaState() ?? new MetaState(false, false);
    }

    /**
     * @return SerializedResultsJob
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->entity?->getState() ?? null,
            'end_state' => $this->entity?->getEndState() ?? null,
            'meta_state' => $this->getMetaState(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => [],
        ];
    }
}
