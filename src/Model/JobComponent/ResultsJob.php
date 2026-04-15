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
 *   preparation: Preparation
 * }
 */
readonly class ResultsJob implements SerializeToArrayInterface, JobComponentInterface
{
    public function __construct(
        private ?ResultsJobEntity $entity,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

    public function getName(): JobComponentName
    {
        return JobComponentName::RESULTS_JOB;
    }

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false);
        }

        if (null === $this->entity) {
            return new MetaState(false, false);
        }

        return $this->entity->getMetaState();
    }

    /**
     * @return SerializedResultsJob
     */
    public function jsonSerialize(): array
    {
        $state = $this->entity?->getState() ?? null;
        $endState = $this->entity?->getEndState() ?? null;

        if ($this->preparation->hasFailure()) {
            $state = 'ended';
            $endState = 'failed';
        }

        return [
            'state' => $state,
            'end_state' => $endState,
            'meta_state' => $this->getMetaState(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => $this->preparation,
        ];
    }
}
