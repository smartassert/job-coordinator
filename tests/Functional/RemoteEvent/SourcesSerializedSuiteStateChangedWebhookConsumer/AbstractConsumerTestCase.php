<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Functional\Application\GetClientAdapterTrait;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\RemoteEventConfigurationFactory;

abstract class AbstractConsumerTestCase extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    protected EventRecorder $eventRecorder;
    protected RemoteEventConfigurationFactory $remoteEventConfigurationFactory;
    protected string $notifySecret;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $remoteEventConfigurationFactory = self::getContainer()->get(RemoteEventConfigurationFactory::class);
        \assert($remoteEventConfigurationFactory instanceof RemoteEventConfigurationFactory);
        $this->remoteEventConfigurationFactory = $remoteEventConfigurationFactory;

        $notifySecret = self::getContainer()->getParameter('sources_notify_secret');
        \assert(is_string($notifySecret));
        $this->notifySecret = $notifySecret;
    }
}
