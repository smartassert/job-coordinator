<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Enum\RemoteRequestFailureType;
use App\Model\RemoteRequestFailure;
use SmartAssert\ServiceClient\Exception\HttpResponseExceptionInterface;

class HttpResponseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $throwable): ?RemoteRequestFailure
    {
        if (!$throwable instanceof HttpResponseExceptionInterface) {
            return null;
        }

        return new RemoteRequestFailure(
            RemoteRequestFailureType::HTTP,
            $throwable->getStatusCode(),
            $throwable->getHttpResponse()->getReasonPhrase()
        );
    }
}
