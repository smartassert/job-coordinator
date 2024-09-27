<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventListener;

use SmartAssert\WorkerMessageFailedEventBundle\WorkerMessageFailedEventHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class EventListenerConfigurationTest extends WebTestCase
{
    /**
     * @dataProvider eventListenersAreDefinedDataProvider
     */
    public function testEventListenersAreDefined(
        string $eventName,
        string $expectedListenerClass,
        string $expectedListenerMethod
    ): void {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $listeners = $eventDispatcher->getListeners($eventName);

        self::assertNotEmpty($listeners, 'Ahh no listeners!');

        $listenerFound = false;
        foreach ($listeners as $listenerConfiguration) {
            \assert(is_array($listenerConfiguration));
            \assert(isset($listenerConfiguration[0]));
            \assert(isset($listenerConfiguration[1]));

            $listenerClass = $listenerConfiguration[0];
            $listenerMethod = $listenerConfiguration[1];

            if (
                is_object($listenerClass)
                && is_string($listenerMethod)
                && $expectedListenerClass === $listenerClass::class
                && $expectedListenerMethod === $listenerMethod
            ) {
                self::assertTrue(
                    method_exists($listenerClass, $expectedListenerMethod),
                    sprintf(
                        'Event listener "%s" does not have expected method "%s".',
                        $expectedListenerClass,
                        $expectedListenerMethod,
                    )
                );

                $listenerFound = true;
            }
        }

        self::assertTrue(
            $listenerFound,
            sprintf(
                '"%s::%s" not found as listener for "%s".',
                $expectedListenerClass,
                $expectedListenerMethod,
                $eventName
            )
        );
    }

    /**
     * @return array<mixed>
     */
    public function eventListenersAreDefinedDataProvider(): array
    {
        return [
            WorkerMessageFailedEventHandler::class . ' listens for ' . WorkerMessageFailedEvent::class => [
                'eventName' => WorkerMessageFailedEvent::class,
                'expectedListenerClass' => WorkerMessageFailedEventHandler::class,
                'expectedListenerMethod' => '__invoke',
            ],
        ];
    }
}
