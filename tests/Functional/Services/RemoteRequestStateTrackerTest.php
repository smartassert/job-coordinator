<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestStateTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class RemoteRequestStateTrackerTest extends WebTestCase
{
    private RemoteRequestStateTracker $remoteRequestStateTracker;
    private RemoteRequestRepository $remoteRequestRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestStateTracker = self::getContainer()->get(RemoteRequestStateTracker::class);
        \assert($remoteRequestStateTracker instanceof RemoteRequestStateTracker);
        $this->remoteRequestStateTracker = $remoteRequestStateTracker;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->remoteRequestRepository = $remoteRequestRepository;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->remoteRequestStateTracker);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->remoteRequestStateTracker::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            WorkerMessageFailedEvent::class => [
                'expectedListenedForEvent' => WorkerMessageFailedEvent::class,
                'expectedMethod' => 'setRemoteRequestStateForMessageFailedEvent',
            ],
            WorkerMessageHandledEvent::class => [
                'expectedListenedForEvent' => WorkerMessageHandledEvent::class,
                'expectedMethod' => 'setRemoteRequestStateForMessageHandledEvent',
            ],
            WorkerMessageReceivedEvent::class => [
                'expectedListenedForEvent' => WorkerMessageReceivedEvent::class,
                'expectedMethod' => 'setRemoteRequestStateForMessageReceivedEvent',
            ],
        ];
    }

    /**
     * @dataProvider setRemoteRequestStateForMessageFailedEventDataProvider
     *
     * @param callable(): WorkerMessageFailedEvent $eventCreator
     */
    public function testSetRemoteRequestStateForMessageFailedEvent(
        callable $eventCreator,
        RequestState $expectedRequestState
    ): void {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $event = $eventCreator();
        $message = $event->getEnvelope()->getMessage();
        \assert($message instanceof JobRemoteRequestMessageInterface);

        $this->remoteRequestStateTracker->setRemoteRequestStateForMessageFailedEvent($event);

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType(), $message->getIndex())
        );

        $expectedRemoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
        $expectedRemoteRequest->setState($expectedRequestState);

        self::assertEquals($expectedRemoteRequest, $remoteRequest);
    }

    /**
     * @return array<mixed>
     */
    public static function setRemoteRequestStateForMessageFailedEventDataProvider(): array
    {
        return [
            WorkerMessageFailedEvent::class . ', will not retry ' => [
                'eventCreator' => function () {
                    return new WorkerMessageFailedEvent(
                        new Envelope(self::createMessage()),
                        'async',
                        new \Exception()
                    );
                },
                'expectedRequestState' => RequestState::FAILED,
            ],
            WorkerMessageFailedEvent::class . ', will retry ' => [
                'eventCreator' => function () {
                    $event = new WorkerMessageFailedEvent(
                        new Envelope(self::createMessage()),
                        'async',
                        new \Exception()
                    );
                    $event->setForRetry();

                    return $event;
                },
                'expectedRequestState' => RequestState::HALTED,
            ],
        ];
    }

    public function testSetRemoteRequestStateForMessageHandledEvent(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $event = new WorkerMessageHandledEvent(new Envelope(self::createMessage()), 'async');
        $message = $event->getEnvelope()->getMessage();
        \assert($message instanceof JobRemoteRequestMessageInterface);

        $this->remoteRequestStateTracker->setRemoteRequestStateForMessageHandledEvent($event);

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType(), $message->getIndex())
        );

        $expectedRemoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
        $expectedRemoteRequest->setState(RequestState::SUCCEEDED);

        self::assertEquals($expectedRemoteRequest, $remoteRequest);
    }

    public function testSetRemoteRequestStateForMessageReceivedEvent(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $event = new WorkerMessageReceivedEvent(new Envelope(self::createMessage()), 'async');
        $message = $event->getEnvelope()->getMessage();
        \assert($message instanceof JobRemoteRequestMessageInterface);

        $this->remoteRequestStateTracker->setRemoteRequestStateForMessageReceivedEvent($event);

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType(), $message->getIndex())
        );

        $expectedRemoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
        $expectedRemoteRequest->setState(RequestState::REQUESTING);

        self::assertEquals($expectedRemoteRequest, $remoteRequest);
    }

    public function testSetRemoteRequestStateForMultipleStateChanges(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $message = self::createMessage();

        $event = new WorkerMessageReceivedEvent(new Envelope($message), 'async');

        $this->remoteRequestStateTracker->setRemoteRequestStateForMessageReceivedEvent($event);
        self::assertSame(1, $this->remoteRequestRepository->count([]));

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType(), $message->getIndex())
        );
        \assert($remoteRequest instanceof RemoteRequest);

        $remoteRequestReflector = new \ReflectionClass($remoteRequest::class);
        $stateProperty = $remoteRequestReflector->getProperty('state');

        self::assertSame(RequestState::REQUESTING, $stateProperty->getValue($remoteRequest));

        $event = new WorkerMessageHandledEvent(new Envelope($message), 'async');

        $this->remoteRequestStateTracker->setRemoteRequestStateForMessageHandledEvent($event);
        self::assertSame(1, $this->remoteRequestRepository->count([]));
        self::assertSame(RequestState::SUCCEEDED, $stateProperty->getValue($remoteRequest));
    }

    private static function createMessage(): JobRemoteRequestMessageInterface
    {
        $jobId = md5((string) rand());

        $message = \Mockery::mock(JobRemoteRequestMessageInterface::class);
        $message
            ->shouldReceive('getJobId')
            ->andReturn($jobId)
        ;

        $message
            ->shouldReceive('getRemoteRequestType')
            ->andReturn(self::getRandomRemoteRequestType())
        ;

        $message
            ->shouldReceive('getIndex')
            ->andReturn(0)
        ;

        return $message;
    }

    private static function getRandomRemoteRequestType(): RemoteRequestType
    {
        $cases = RemoteRequestType::cases();

        return $cases[rand(0, count($cases) - 1)];
    }
}
