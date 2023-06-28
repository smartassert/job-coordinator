<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure;
use App\Enum\RequestState;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailure
 *
 * @phpstan-type SerializedRemoteRequest array{
 *   state: value-of<RequestState>,
 *   failure?: SerializedRemoteRequestFailure
 * }
 */
interface SerializableRemoteRequestInterface
{
    /**
     * @return SerializedRemoteRequest
     */
    public function toArray(): array;
}
