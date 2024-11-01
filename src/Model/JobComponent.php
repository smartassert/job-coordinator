<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;

readonly class JobComponent
{
    public function __construct(
        public JobComponentName $name,
        public RemoteRequestType $remoteRequestType,
    ) {
    }
}
