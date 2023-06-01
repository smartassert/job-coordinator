<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Enum\RemoteRequestFailureType;
use App\Model\RemoteRequestFailure;

class ThrowableExceptionHandler implements ExceptionHandlerInterface
{
    public static function getDefaultPriority(): int
    {
        return -1;
    }

    public function handle(\Throwable $throwable): ?RemoteRequestFailure
    {
        return new RemoteRequestFailure(
            RemoteRequestFailureType::UNKNOWN,
            $throwable->getCode(),
            $throwable->getMessage()
        );
    }
}
