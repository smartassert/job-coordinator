<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\RemoteRequestEntity;

readonly class JobComponent
{
    public function __construct(
        public JobComponentName    $name,
        public RemoteRequestEntity $remoteRequestEntity,
    ) {
    }
}
