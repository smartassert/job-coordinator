<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Event\JobCreatedEvent;
use App\Tests\Services\EventSubscriber\EventRecorder;

abstract class AbstractCreateJobDispatchedEventsTest extends AbstractCreateJobSuccessSetup
{
    private static EventRecorder $eventRecorder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        self::$eventRecorder = $eventRecorder;
    }

    public function testDispatchedEventCount(): void
    {
        self::assertCount(1, self::$eventRecorder);
    }

    public function testJobCreatedEventIsDispatched(): void
    {
        $event = self::$eventRecorder->getLatest();
        self::assertInstanceOf(JobCreatedEvent::class, $event);

        $jobId = self::$createResponseData['id'] ?? null;
        \assert(is_string($jobId) && '' !== $jobId);
        self::assertEquals(new JobCreatedEvent(self::$apiToken, $jobId, []), $event);
    }
}
