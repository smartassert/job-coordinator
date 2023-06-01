<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RemoteRequestFailureType;

class RemoteRequestFailure
{
    public readonly RemoteRequestFailureType $type;
    public int $code;
    public ?string $message = null;

    public function __construct(RemoteRequestFailureType $type, int $code, ?string $message)
    {
        $this->type = $type;
        $this->code = $code;
        $this->message = $message;
    }
}
