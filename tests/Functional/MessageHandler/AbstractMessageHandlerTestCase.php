<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
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
        self::assertCount(1, $invokeMethodParameters);

        $messageParameter = $invokeMethodParameters[0];

        $messageParameterType = $messageParameter->getType();
        \assert($messageParameterType instanceof \ReflectionNamedType);

        self::assertSame($this->getHandledMessageClass(), $messageParameterType->getName());
    }

    protected function assertExpectedNotYetHandleableOutcome(JobRemoteRequestMessageInterface $message): void
    {
        $this->assertEventOutcome($message, MessageNotYetHandleableEvent::class);
    }

    protected function assertExpectedNotHandleableOutcome(JobRemoteRequestMessageInterface $message): void
    {
        $this->assertEventOutcome($message, MessageNotHandleableEvent::class);
    }

    /**
     * @return class-string
     */
    abstract protected function getHandlerClass(): string;

    /**
     * @return class-string
     */
    abstract protected function getHandledMessageClass(): string;

    /**
     * @param class-string $expectedEventClass
     */
    private function assertEventOutcome(JobRemoteRequestMessageInterface $message, string $expectedEventClass): void
    {
        $events = $this->eventRecorder->all($expectedEventClass);
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf($expectedEventClass, $event);
        self::assertObjectHasProperty('message', $event);
        self::assertSame($message, $event->message);
    }
}
