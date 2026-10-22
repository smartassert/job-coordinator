<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 *
 * @phpstan-type SerializedWorkerComponentState array{
 *   state: ?non-empty-string,
 *   meta_state: SerializedMetaState
 * }
 */
interface WorkerComponentStateInterface extends SerializeToArrayInterface
{
    public function getState(): string;

    public function getMetaState(): MetaState;

    /**
     * @return SerializedWorkerComponentState
     */
    public function toArray(): array;
}
