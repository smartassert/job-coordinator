<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface as UnrecoverableException;

class UnrecoverableRemoteJobActionException extends RemoteJobActionException implements UnrecoverableException {}
