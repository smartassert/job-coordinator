<?php

declare(strict_types=1);

namespace App\Tests\Services\Mock;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
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
            ->withArgs(function (JobRemoteRequestMessageInterface $message) use ($type, $jobId) {
                TestCase::assertTrue($message->getRemoteRequestType()->equals($type));
                TestCase::assertSame($message->getJobId(), $jobId);

                return true;
            })
            ->andReturn($readiness)
        ;

        return $assessor;
    }
}
