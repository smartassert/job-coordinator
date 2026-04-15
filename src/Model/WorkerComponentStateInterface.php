<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedWorkerComponentState array{
 *   state: ?non-empty-string,
 *   meta_state: MetaState
 * }
 */
interface WorkerComponentStateInterface
{
    /**
     * @return SerializedWorkerComponentState
     */
    public function toArray(): array;

    public function getMetaState(): MetaState;
}
