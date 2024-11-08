<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\CreateMachineMessage;
use App\Message\GetResultsJobStateMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class JobRemoteRequestMessageDispatcherTest extends WebTestCase
{
    private JobRemoteRequestMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;
    private JobFactory $jobFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($dispatcher instanceof JobRemoteRequestMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->jobFactory = $jobFactory;
    }

    /**
     * @param callable(Job): JobRemoteRequestMessageInterface $messageCreator
     * @param StampInterface[]                                $stamps
     */
    #[DataProvider('dispatchSuccessDataProvider')]
    public function testDispatchSuccess(callable $messageCreator, array $stamps): void
    {
        $job = $this->jobFactory->createRandom();

        $message = $messageCreator($job);
        $this->dispatcher->dispatch($message, $stamps);

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        self::assertEquals(
            [
                new JobRemoteRequestMessageCreatedEvent($message),
            ],
            $eventRecorder->all(JobRemoteRequestMessageCreatedEvent::class)
        );

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertSame($envelope->getMessage(), $message);

        foreach ($stamps as $stamp) {
            self::assertSame([$stamp], $envelope->all($stamp::class));
        }
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchSuccessDataProvider(): array
    {
        return [
            'without stamps' => [
                'messageCreator' => function (Job $job) {
                    return new GetResultsJobStateMessage('api token', $job->id);
                },
                'stamps' => [],
            ],
            'with stamps' => [
                'messageCreator' => function (Job $job) {
                    return new GetResultsJobStateMessage('api token', $job->id);
                },
                'stamps' => [
                    new NonDelayedStamp(),
                ],
            ],
            'repeatable message with no existing requests' => [
                'messageCreator' => function (Job $job) {
                    return new CreateMachineMessage('api token', $job->id);
                },
                'stamps' => [],
            ],
        ];
    }

    /**
     * @param callable(Job): JobRemoteRequestMessageInterface $messageCreator
     * @param callable(RemoteRequestRepository, Job): void    $remoteRequestCreator
     */
    #[DataProvider('dispatchDisallowedRepeatableMessageDataProvider')]
    public function testDispatchDisallowedRepeatableMessage(
        callable $messageCreator,
        callable $remoteRequestCreator,
    ): void {
        $job = $this->jobFactory->createRandom();

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $remoteRequestCreator($remoteRequestRepository, $job);

        $message = $messageCreator($job);
        $this->dispatcher->dispatch($message);

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        self::assertEquals([], $eventRecorder->all(JobRemoteRequestMessageCreatedEvent::class));

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchDisallowedRepeatableMessageDataProvider(): array
    {
        return [
            'has existing successful request' => [
                'messageCreator' => function (Job $job) {
                    return new CreateMachineMessage('api token', $job->id);
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    Job $job,
                ): void {
                    $remoteRequest = new RemoteRequest(
                        $job->id,
                        new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE)
                    );

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
            'newest remote request has requesting state' => [
                'messageCreator' => function (Job $job) {
                    return new CreateMachineMessage('api token', $job->id);
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    Job $job,
                ): void {
                    $remoteRequest = new RemoteRequest(
                        $job->id,
                        new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE),
                    );
                    $remoteRequest->setState(RequestState::REQUESTING);

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
            'newest remote request has pending state' => [
                'messageCreator' => function (Job $job) {
                    return new CreateMachineMessage('api token', $job->id);
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    Job $job,
                ): void {
                    $remoteRequest = new RemoteRequest(
                        $job->id,
                        new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE),
                    );
                    $remoteRequest->setState(RequestState::PENDING);

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
        ];
    }
}
