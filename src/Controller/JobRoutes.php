<?php

declare(strict_types=1);

namespace App\Controller;

class JobRoutes
{
    public const SUITE_ID_ATTRIBUTE = 'suiteId';
    public const ROUTE_SUITE_ID_PATTERN = '{' . self::SUITE_ID_ATTRIBUTE . '<[A-Z90-9]{26}>}';
    public const ROUTE_JOB_ID_PATTERN = '{jobId<[A-Z90-9]{26}>}';
}
