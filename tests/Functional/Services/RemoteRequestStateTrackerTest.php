<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class RemoteRequestStateTrackerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
    }

    /**
     * @param callable(JobRemoteRequestMessageInterface, RemoteRequestRepository): void $remoteRequestCreator
     * @param callable(JobRemoteRequestMessageInterface): object                        $eventCreator
     */
    #[DataProvider('handleRemoteRequestMessageStateChangeDataProvider')]
    public function testHandleRemoteRequestMessageStateChange(
        callable $remoteRequestCreator,
        MessageState $messageState,
        ?RequestState $expectedPreState,
        callable $eventCreator,
        RequestState $expectedPostState,
    ): void {
        $message = self::createMessage($messageState);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $remoteRequestCreator($message, $remoteRequestRepository);

        $remoteRequest = $remoteRequestRepository->findOneBy(['jobId' => $message->getJobId()]);
        self::assertSame($expectedPreState, $remoteRequest?->getState());

        $event = $eventCreator($message);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $eventDispatcher->dispatch($event);

        $remoteRequest = $remoteRequestRepository->findOneBy(['jobId' => $message->getJobId()]);
        self::assertSame($expectedPostState, $remoteRequest?->getState());
    }

    /**
     * @return array<mixed>
     */
    public static function handleRemoteRequestMessageStateChangeDataProvider(): array
    {
        return [
            WorkerMessageFailedEvent::class . ', no pre-existing state, will not retry ' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageFailedEvent(
                        new Envelope($message),
                        'async',
                        new \Exception()
                    );
                },
                'expectedPostState' => RequestState::FAILED,
            ],
            WorkerMessageFailedEvent::class . ', no pre-existing state, will retry ' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageFailedEvent(
                        new Envelope($message),
                        'async',
                        new \Exception()
                    );
                },
                'expectedPostState' => RequestState::FAILED,
            ],
            WorkerMessageFailedEvent::class . ', no has-existing state, will not retry ' => [
                'remoteRequestCreator' => function (
                    JobRemoteRequestMessageInterface $message,
                    RemoteRequestRepository $remoteRequestRepository
                ) {
                    $remoteRequest = new RemoteRequest(
                        $message->getJobId(),
                        $message->getRemoteRequestType(),
                    );
                    $remoteRequestRepository->save($remoteRequest);
                },
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => RequestState::REQUESTING,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    $event = new WorkerMessageFailedEvent(
                        new Envelope($message),
                        'async',
                        new \Exception()
                    );
                    $event->setForRetry();

                    return $event;
                },
                'expectedPostState' => RequestState::HALTED,
            ],
            WorkerMessageHandledEvent::class . ' no pre-existing state, message state handling' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageHandledEvent(
                        new Envelope($message),
                        'async',
                    );
                },
                'expectedPostState' => RequestState::SUCCEEDED,
            ],
            WorkerMessageHandledEvent::class . ' no pre-existing state, message state halted' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HALTED,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageHandledEvent(
                        new Envelope($message),
                        'async',
                    );
                },
                'expectedPostState' => RequestState::HALTED,
            ],
            WorkerMessageHandledEvent::class . ' no pre-existing state, message state stopped' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::STOPPED,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageHandledEvent(
                        new Envelope($message),
                        'async',
                    );
                },
                'expectedPostState' => RequestState::FAILED,
            ],
            WorkerMessageReceivedEvent::class . ' no pre-existing state' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new WorkerMessageReceivedEvent(
                        new Envelope($message),
                        'async',
                    );
                },
                'expectedPostState' => RequestState::REQUESTING,
            ],
            JobRemoteRequestMessageCreatedEvent::class . ' no pre-existing state' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new JobRemoteRequestMessageCreatedEvent($message);
                },
                'expectedPostState' => RequestState::REQUESTING,
            ],
            MessageNotHandleableEvent::class . ' no pre-existing state, not yet handleable' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new MessageNotHandleableEvent(
                        $message,
                        MessageHandlingReadiness::EVENTUALLY,
                    );
                },
                'expectedPostState' => RequestState::HALTED,
            ],
            MessageNotHandleableEvent::class . ' no pre-existing state, never handleable' => [
                'remoteRequestCreator' => function () {},
                'messageState' => MessageState::HANDLING,
                'expectedPreState' => null,
                'eventCreator' => function (JobRemoteRequestMessageInterface $message) {
                    return new MessageNotHandleableEvent(
                        $message,
                        MessageHandlingReadiness::NEVER,
                    );
                },
                'expectedPostState' => RequestState::ABORTED,
            ],
        ];
    }

    private static function createMessage(MessageState $state): JobRemoteRequestMessageInterface
    {
        $jobId = md5((string) rand());

        $message = \Mockery::mock(JobRemoteRequestMessageInterface::class);
        $message
            ->shouldReceive('getJobId')
            ->andReturn($jobId)
        ;

        $message
            ->shouldReceive('getRemoteRequestType')
            ->andReturn(RemoteRequestType::createForMachineCreation())
        ;

        $message
            ->shouldReceive('getIndex')
            ->andReturn(0)
        ;

        $message
            ->shouldReceive('getState')
            ->andReturn($state)
        ;

        $message
            ->shouldReceive('setIndex')
            ->andReturn($message)
        ;

        return $message;
    }
}
