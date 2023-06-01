<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Model\RemoteRequestFailure;

interface ExceptionHandlerInterface
{
    public function handle(\Throwable $throwable): ?RemoteRequestFailure;
}
