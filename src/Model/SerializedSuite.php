<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\SerializedSuite as SerializedSuiteEntity;

/**
 * @phpstan-type SerializedSerializedSuite array{state: ?non-empty-string}
 */
class SerializedSuite
{
    public function __construct(
        private readonly SerializedSuiteEntity $entity,
    ) {
    }

    /**
     * @return SerializedSerializedSuite
     */
    public function toArray(): array
    {
        return [
            'state' => $this->entity->getState(),
        ];
    }
}
