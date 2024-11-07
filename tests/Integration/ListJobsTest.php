<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractListJobsTest;
use App\Tests\Services\EntityRemover;

class ListJobsTest extends AbstractListJobsTest
{
    use GetClientAdapterTrait;

    public static function tearDownAfterClass(): void
    {
        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllJobs();
        $entityRemover->removeAllRemoteRequests();
    }
}
