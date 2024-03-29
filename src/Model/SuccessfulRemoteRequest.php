<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RequestState;

class SuccessfulRemoteRequest extends RemoteRequest
{
    public function __construct()
    {
        parent::__construct(RequestState::SUCCEEDED);
    }
}
