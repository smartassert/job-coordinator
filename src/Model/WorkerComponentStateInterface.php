<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedWorkerComponentState array{
 *   state: non-empty-string,
 *   is_end_state: bool
 * }
 */
interface WorkerComponentStateInterface
{
    /**
     * @return SerializedWorkerComponentState
     */
    public function toArray(): array;
}
