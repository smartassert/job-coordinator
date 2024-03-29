<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailureEntity
 *
 * @phpstan-type SerializedComponentFailures array<non-empty-string, SerializedRemoteRequestFailure|null>
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

    public function has(): bool
    {
        return 0 !== count($this->componentFailures);
    }

    /**
     * @return SerializedComponentFailures
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->componentFailures as $componentFailure) {
            if ($componentFailure instanceof ComponentFailure) {
                $failure = $componentFailure->failure;
                $failureData = $failure instanceof RemoteRequestFailureEntity ? $failure->toArray() : null;

                $data[$componentFailure->componentName] = $failureData;
            }
        }

        return $data;
    }
}
