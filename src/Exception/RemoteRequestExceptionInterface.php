<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

interface RemoteRequestExceptionInterface extends \Throwable
{
    public function getJob(): Job;

    public function getPreviousException(): \Throwable;
}
