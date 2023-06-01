<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Enum\RemoteRequestFailureType;
use App\Model\RemoteRequestFailure;
use Psr\Http\Client\NetworkExceptionInterface;

class NetworkExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $throwable): ?RemoteRequestFailure
    {
        if (!$throwable instanceof NetworkExceptionInterface) {
            return null;
        }

        return new RemoteRequestFailure(
            RemoteRequestFailureType::NETWORK,
            $throwable->getCode(),
            $throwable->getMessage()
        );
    }
}
