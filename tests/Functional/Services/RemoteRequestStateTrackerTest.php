<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFactory;
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
    public function testEventSubscriptions(string $expectedListenedForEvent): void
    {
        $subscribedEvents = $this->remoteRequestStateTracker::getSubscribedEvents();
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
            WorkerMessageFailedEvent::class => [
                'expectedListenedForEvent' => WorkerMessageFailedEvent::class,
            ],
            WorkerMessageHandledEvent::class => [
                'expectedListenedForEvent' => WorkerMessageHandledEvent::class,
            ],
            WorkerMessageReceivedEvent::class => [
                'expectedListenedForEvent' => WorkerMessageReceivedEvent::class,
            ],
        ];
    }

    /**
     * @dataProvider setRemoteRequestStateDataProvider
     *
     * @param callable(JobRemoteRequestMessageInterface): RemoteRequest $expectedRemoteRequestCreator
     */
    public function testSetRemoteRequestState(callable $eventCreator, callable $expectedRemoteRequestCreator): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $remoteRequestFactory = self::getContainer()->get(RemoteRequestFactory::class);
        \assert($remoteRequestFactory instanceof RemoteRequestFactory);

        $event = $eventCreator();
        $message = $event->getEnvelope()->getMessage();
        \assert($message instanceof JobRemoteRequestMessageInterface);

        $this->remoteRequestStateTracker->setRemoteRequestState($event);

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType())
        );

        $expectedRemoteRequest = $expectedRemoteRequestCreator($message);

        self::assertEquals($expectedRemoteRequest, $remoteRequest);
    }

    /**
     * @return array<mixed>
     */
    public function setRemoteRequestStateDataProvider(): array
    {
        return [
            WorkerMessageFailedEvent::class . ', will not retry ' => [
                'eventCreator' => function () {
                    return new WorkerMessageFailedEvent(
                        new Envelope($this->createMessage()),
                        'async',
                        new \Exception()
                    );
                },
                'expectedRemoteRequestCreator' => function (JobRemoteRequestMessageInterface $message) {
                    $remoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
                    $remoteRequest->setState(RequestState::FAILED);

                    return $remoteRequest;
                },
            ],
            WorkerMessageFailedEvent::class . ', will retry ' => [
                'eventCreator' => function () {
                    $event = new WorkerMessageFailedEvent(
                        new Envelope($this->createMessage()),
                        'async',
                        new \Exception()
                    );
                    $event->setForRetry();

                    return $event;
                },
                'expectedRemoteRequestCreator' => function (JobRemoteRequestMessageInterface $message) {
                    $remoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
                    $remoteRequest->setState(RequestState::HALTED);

                    return $remoteRequest;
                },
            ],
            WorkerMessageHandledEvent::class => [
                'eventCreator' => function () {
                    return new WorkerMessageHandledEvent(new Envelope($this->createMessage()), 'async');
                },
                'expectedRemoteRequestCreator' => function (JobRemoteRequestMessageInterface $message) {
                    $remoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
                    $remoteRequest->setState(RequestState::SUCCEEDED);

                    return $remoteRequest;
                },
            ],
            WorkerMessageReceivedEvent::class => [
                'eventCreator' => function () {
                    return new WorkerMessageReceivedEvent(new Envelope($this->createMessage()), 'async');
                },
                'expectedRemoteRequestCreator' => function (JobRemoteRequestMessageInterface $message) {
                    $remoteRequest = new RemoteRequest($message->getJobId(), $message->getRemoteRequestType());
                    $remoteRequest->setState(RequestState::REQUESTING);

                    return $remoteRequest;
                },
            ],
        ];
    }

    public function testSetRemoteRequestStateForMultipleStateChanges(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $remoteRequestFactory = self::getContainer()->get(RemoteRequestFactory::class);
        \assert($remoteRequestFactory instanceof RemoteRequestFactory);

        $message = $this->createMessage();

        $event = new WorkerMessageReceivedEvent(new Envelope($message), 'async');

        $this->remoteRequestStateTracker->setRemoteRequestState($event);
        self::assertSame(1, $this->remoteRequestRepository->count([]));

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($message->getJobId(), $message->getRemoteRequestType())
        );
        \assert($remoteRequest instanceof RemoteRequest);

        $remoteRequestReflector = new \ReflectionClass($remoteRequest::class);
        $stateProperty = $remoteRequestReflector->getProperty('state');

        self::assertSame(RequestState::REQUESTING, $stateProperty->getValue($remoteRequest));

        $event = new WorkerMessageHandledEvent(new Envelope($message), 'async');

        $this->remoteRequestStateTracker->setRemoteRequestState($event);
        self::assertSame(1, $this->remoteRequestRepository->count([]));
        self::assertSame(RequestState::SUCCEEDED, $stateProperty->getValue($remoteRequest));
    }

    private function createMessage(): JobRemoteRequestMessageInterface
    {
        $jobId = md5((string) rand());

        $message = \Mockery::mock(JobRemoteRequestMessageInterface::class);
        $message
            ->shouldReceive('getJobId')
            ->andReturn($jobId)
        ;

        $message
            ->shouldReceive('getRemoteRequestType')
            ->andReturn($this->getRandomRemoteRequestType())
        ;

        return $message;
    }

    private function getRandomRemoteRequestType(): RemoteRequestType
    {
        $cases = RemoteRequestType::cases();

        return $cases[rand(0, count($cases) - 1)];
    }
}
