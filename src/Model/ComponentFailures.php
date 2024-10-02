<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailureEntity
 *
 * @phpstan-type SerializedComponentFailures array<non-empty-string, RemoteRequestFailureEntity|null>
 */
class ComponentFailures implements \JsonSerializable
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
    public function jsonSerialize(): array
    {
        $data = [];

        foreach ($this->componentFailures as $componentFailure) {
            $data[$componentFailure->componentName] = $componentFailure->failure;
        }

        return $data;
    }
}
