<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Tests\Functional\RemoteEvent\AbstractConsumerTestCase as BaseTest;

abstract class AbstractConsumerTestCase extends BaseTest
{
    protected string $notifySecret;

    protected function setUp(): void
    {
        parent::setUp();

        $notifySecret = self::getContainer()->getParameter('sources_notify_secret');
        \assert(is_string($notifySecret));
        $this->notifySecret = $notifySecret;
    }
}
