<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Event\JobCreatedEvent;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EventSubscriber\EventRecorder;

class CreateJobDispatchedEventsTest extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;

    private static EventRecorder $eventRecorder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        self::$eventRecorder = $eventRecorder;
    }

    public function testJobCreatedEventIsDispatched(): void
    {
        $events = self::$eventRecorder->all(JobCreatedEvent::class);
        $event = $events[0];
        self::assertInstanceOf(JobCreatedEvent::class, $event);

        $jobId = self::$createResponseData['id'] ?? null;
        \assert(is_string($jobId) && '' !== $jobId);

        $suiteId = self::$createResponseData['suite_id'] ?? null;
        \assert(is_string($suiteId) && '' !== $suiteId);

        self::assertEquals(new JobCreatedEvent(self::$apiToken, $jobId, $suiteId, []), $event);
    }
}
