<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\CreateMachineMessage;
use App\Message\GetResultsJobStateMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
     * @param callable(JobInterface): JobRemoteRequestMessageInterface $messageCreator
     * @param StampInterface[]                                         $stamps
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
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
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
                'messageCreator' => function (JobInterface $job) {
                    return new GetResultsJobStateMessage('api token', $job->getId());
                },
                'stamps' => [],
            ],
            'with stamps' => [
                'messageCreator' => function (JobInterface $job) {
                    return new GetResultsJobStateMessage('api token', $job->getId());
                },
                'stamps' => [
                    new NonDelayedStamp(),
                ],
            ],
            'repeatable message with no existing requests' => [
                'messageCreator' => function (JobInterface $job) {
                    return new CreateMachineMessage('api token', $job->getId());
                },
                'stamps' => [],
            ],
        ];
    }

    /**
     * @param callable(JobInterface): JobRemoteRequestMessageInterface $messageCreator
     * @param callable(RemoteRequestRepository, JobInterface): void    $remoteRequestCreator
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
        self::assertCount(0, $this->messengerTransport->getSent());
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchDisallowedRepeatableMessageDataProvider(): array
    {
        return [
            'has existing successful request' => [
                'messageCreator' => function (JobInterface $job) {
                    return new CreateMachineMessage('api token', $job->getId());
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    JobInterface $job,
                ): void {
                    $remoteRequest = new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation());

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
            'newest remote request has requesting state' => [
                'messageCreator' => function (JobInterface $job) {
                    return new CreateMachineMessage('api token', $job->getId());
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    JobInterface $job,
                ): void {
                    $remoteRequest = new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation());
                    $remoteRequest->setState(RequestState::REQUESTING);

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
            'newest remote request has pending state' => [
                'messageCreator' => function (JobInterface $job) {
                    return new CreateMachineMessage('api token', $job->getId());
                },
                'remoteRequestCreator' => function (
                    RemoteRequestRepository $remoteRequestRepository,
                    JobInterface $job,
                ): void {
                    $remoteRequest = new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation());
                    $remoteRequest->setState(RequestState::PENDING);

                    $remoteRequestRepository->save($remoteRequest);
                },
            ],
        ];
    }
}
