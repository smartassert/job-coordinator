<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class JobComponent
{
    public function __construct(
        public readonly JobComponentName $name,
        public readonly RemoteRequestEntity $remoteRequestEntity,
        public readonly RemoteRequestAction $remoteRequestAction,
    ) {
    }
}
