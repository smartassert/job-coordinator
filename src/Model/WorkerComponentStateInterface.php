<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedWorkerComponentState array{
 *   state: ?non-empty-string,
 *   meta_state: MetaState
 * }
 */
interface WorkerComponentStateInterface extends \JsonSerializable
{
    public function getState(): string;

    public function getMetaState(): MetaState;

    /**
     * @return SerializedWorkerComponentState
     */
    public function jsonSerialize(): array;
}
