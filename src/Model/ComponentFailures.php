<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailureEntity
 *
 * @phpstan-type SerializedComponentFailures array<non-empty-string, RemoteRequestFailureEntity|null>
 */
class ComponentFailures
{
    /**
     * @param ComponentFailure[] $componentFailures
     */
    public function __construct(
        public readonly array $componentFailures,
    ) {
    }

    /**
     * @return SerializedComponentFailures
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->componentFailures as $componentFailure) {
            $failure = $componentFailure->failure;

            $data[$componentFailure->componentName] = $failure instanceof RemoteRequestFailureEntity
                ? $failure
                : null;
        }

        return $data;
    }
}
