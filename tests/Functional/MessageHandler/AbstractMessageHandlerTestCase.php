<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

abstract class AbstractMessageHandlerTestCase extends WebTestCase
{
    /**
     * @var non-empty-string
     */
    protected static string $apiToken;

    protected EventRecorder $eventRecorder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        self::$apiToken = $apiTokenProvider->get('user1@example.com');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handlerClass = $this->getHandlerClass();

        $handler = self::getContainer()->get($handlerClass);
        self::assertInstanceOf($handlerClass, $handler);
        self::assertCount(1, (new \ReflectionClass($handlerClass))->getAttributes(AsMessageHandler::class));
    }

    public function testHandlesExpectedMessage(): void
    {
        $handlerClass = $this->getHandlerClass();

        $handler = self::getContainer()->get($handlerClass);
        \assert($handler instanceof $handlerClass);

        $invokeMethod = (new \ReflectionClass($handler::class))->getMethod('__invoke');

        $invokeMethodParameters = $invokeMethod->getParameters();
        self::assertIsArray($invokeMethodParameters);
        self::assertCount(1, $invokeMethodParameters);

        $messageParameter = $invokeMethodParameters[0];
        \assert($messageParameter instanceof \ReflectionParameter);

        $messageParameterType = $messageParameter->getType();
        \assert($messageParameterType instanceof \ReflectionNamedType);

        self::assertSame($this->getHandledMessageClass(), $messageParameterType->getName());
    }

    /**
     * @return class-string
     */
    abstract protected function getHandlerClass(): string;

    /**
     * @return class-string
     */
    abstract protected function getHandledMessageClass(): string;
}
