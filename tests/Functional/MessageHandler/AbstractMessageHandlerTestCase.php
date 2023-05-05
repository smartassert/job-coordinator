<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

abstract class AbstractMessageHandlerTestCase extends WebTestCase
{
    /**
     * @var non-empty-string
     */
    protected static string $apiToken;

    protected InMemoryTransport $messengerTransport;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        self::$apiToken = $apiTokenProvider->get('user@example.com');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
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

    protected function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }
}
