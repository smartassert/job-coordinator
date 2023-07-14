<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\RemoteRequestType;

class JobComponent
{
    public function __construct(
        public readonly JobComponentName $name,
        public readonly RemoteRequestType $requestType
    ) {
    }
}
