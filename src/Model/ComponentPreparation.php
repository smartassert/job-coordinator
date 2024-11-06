<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Enum\JobComponent;
use App\Enum\PreparationState;

class ComponentPreparation
{
    public function __construct(
        public readonly JobComponent $jobComponent,
        public readonly PreparationState $state,
        public readonly ?RemoteRequestFailureEntity $failure = null,
    ) {
    }
}
