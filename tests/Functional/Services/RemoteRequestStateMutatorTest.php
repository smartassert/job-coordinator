<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Event\RemoteRequestCancelledEvent;
use App\Event\RemoteRequestCompletedEvent;
use App\Event\RemoteRequestEventInterface;
use App\Event\RemoteRequestFailedEvent;
use App\Event\RemoteRequestStartedEvent;
use App\Services\RemoteRequestFactory;
use App\Services\RemoteRequestStateMutator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestStateMutatorTest extends WebTestCase
{
    private RemoteRequestStateMutator $remoteRequestStateMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestStateMutator = self::getContainer()->get(RemoteRequestStateMutator::class);
        \assert($remoteRequestStateMutator instanceof RemoteRequestStateMutator);
        $this->remoteRequestStateMutator = $remoteRequestStateMutator;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->remoteRequestStateMutator);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent): void
    {
        $subscribedEvents = $this->remoteRequestStateMutator::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame('setRemoteRequestState', $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
    {
        return [
            RemoteRequestCancelledEvent::class => [
                'expectedListenedForEvent' => RemoteRequestCancelledEvent::class,
            ],
            RemoteRequestCompletedEvent::class => [
                'expectedListenedForEvent' => RemoteRequestCompletedEvent::class,
            ],
            RemoteRequestFailedEvent::class => [
                'expectedListenedForEvent' => RemoteRequestFailedEvent::class,
            ],
            RemoteRequestStartedEvent::class => [
                'expectedListenedForEvent' => RemoteRequestStartedEvent::class,
            ],
        ];
    }

    /**
     * @dataProvider setRemoteRequestStateDataProvider
     *
     * @param callable(RemoteRequest): RemoteRequestEventInterface $eventCreator
     */
    public function testSetRemoteRequestState(callable $eventCreator, RequestState $expected): void
    {
        $remoteRequestFactory = self::getContainer()->get(RemoteRequestFactory::class);
        \assert($remoteRequestFactory instanceof RemoteRequestFactory);

        $jobId = md5((string) rand());
        $remoteRequest = $remoteRequestFactory->create($jobId, RemoteRequestType::RESULTS_CREATE);
        $event = $eventCreator($remoteRequest);

        $this->remoteRequestStateMutator->setRemoteRequestState($event);

        $remoteRequestReflector = new \ReflectionClass($remoteRequest);
        $stateProperty = $remoteRequestReflector->getProperty('state');
        self::assertSame($expected, $stateProperty->getValue($remoteRequest));
    }

    /**
     * @return array<mixed>
     */
    public function setRemoteRequestStateDataProvider(): array
    {
        return [
            RemoteRequestCancelledEvent::class => [
                'eventCreator' => function (RemoteRequest $remoteRequest) {
                    return new RemoteRequestCancelledEvent($remoteRequest);
                },
                'expected' => RequestState::FAILED,
            ],
            RemoteRequestCompletedEvent::class => [
                'eventCreator' => function (RemoteRequest $remoteRequest) {
                    return new RemoteRequestCompletedEvent($remoteRequest);
                },
                'expected' => RequestState::SUCCEEDED,
            ],
            RemoteRequestFailedEvent::class => [
                'eventCreator' => function (RemoteRequest $remoteRequest) {
                    return new RemoteRequestFailedEvent($remoteRequest);
                },
                'expected' => RequestState::HALTED,
            ],
            RemoteRequestStartedEvent::class => [
                'eventCreator' => function (RemoteRequest $remoteRequest) {
                    return new RemoteRequestStartedEvent($remoteRequest);
                },
                'expected' => RequestState::REQUESTING,
            ],
        ];
    }
}
