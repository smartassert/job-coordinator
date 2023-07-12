<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;

class ComponentFailure
{
    /**
     * @param non-empty-string $componentName
     */
    public function __construct(
        public readonly string $componentName,
        public readonly ?RemoteRequestFailureEntity $failure,
    ) {
    }
}
