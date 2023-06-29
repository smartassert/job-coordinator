<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RequestState;

class PendingRemoteRequest extends RemoteRequest
{
    public function __construct()
    {
        parent::__construct(RequestState::PENDING);
    }
}
