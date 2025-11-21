<?php

declare(strict_types=1);

namespace App\Tests\Services\Mock;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use PHPUnit\Framework\TestCase;

class ReadinessAssessorFactory
{
    public static function create(
        RemoteRequestType $type,
        string $jobId,
        MessageHandlingReadiness $readiness,
    ): ReadinessAssessorInterface {
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->withArgs(function (RemoteRequestType $passedType, string $passedJobId) use ($type, $jobId) {
                TestCase::assertTrue($passedType->equals($type));
                TestCase::assertSame($passedJobId, $jobId);

                return true;
            })
            ->andReturn($readiness)
        ;

        return $assessor;
    }
}
