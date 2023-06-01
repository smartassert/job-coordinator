<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Enum\RemoteRequestFailureType;
use App\Model\RemoteRequestFailure;
use SmartAssert\ServiceClient\Exception\CurlExceptionInterface;

class CurlExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $throwable): ?RemoteRequestFailure
    {
        if (!$throwable instanceof CurlExceptionInterface) {
            return null;
        }

        return new RemoteRequestFailure(
            RemoteRequestFailureType::NETWORK,
            $throwable->getCurlCode(),
            $throwable->getCurlMessage()
        );
    }
}
