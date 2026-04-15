<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure;
use App\Enum\RequestState;

/**
 * @phpstan-type SerializedRemoteRequest array{
 *   state: value-of<RequestState>,
 *   failure?: RemoteRequestFailure
 * }
 */
interface RemoteRequestInterface extends \JsonSerializable
{
    /**
     * @return SerializedRemoteRequest
     */
    public function jsonSerialize(): array;
}
