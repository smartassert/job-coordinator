<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractCreateJobSuccessTest;
use App\Tests\Services\EntityRemover;

class CreateJobSuccessTest extends AbstractCreateJobSuccessTest
{
    use GetClientAdapterTrait;
    use CreateSuiteIdTrait;

    public static function tearDownAfterClass(): void
    {
        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllJobs();
        $entityRemover->removeAllRemoteRequests();
    }
}
