<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent;

use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Functional\Application\GetClientAdapterTrait;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\SymfonyRemoteEventRequestFactory\Factory;

abstract class AbstractConsumerTestCase extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    protected EventRecorder $eventRecorder;
    protected Factory $remoteEventRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $remoteEventRequestFactory = self::getContainer()->get(Factory::class);
        \assert($remoteEventRequestFactory instanceof Factory);
        $this->remoteEventRequestFactory = $remoteEventRequestFactory;
    }
}
