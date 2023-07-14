<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;

class ComponentPreparation
{
    public function __construct(
        public readonly RemoteRequestType $requestType,
        public readonly PreparationState $state,
        public readonly ?RemoteRequestFailureEntity $failure = null,
    ) {
    }
}
