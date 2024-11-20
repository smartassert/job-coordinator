<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobCreatedEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateResultsJobMessage;
use App\MessageDispatcher\CreateResultsJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateResultsJobMessageDispatcherTest extends WebTestCase
{
    private CreateResultsJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateResultsJobMessageDispatcher::class);
        \assert($dispatcher instanceof CreateResultsJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertArrayHasKey(JobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());

        $event = new JobCreatedEvent($authenticationToken, $job->getId(), $job->getSuiteId(), []);

        $this->dispatcher->dispatchForJobCreatedEvent($event);

        $this->assertNonDelayedMessageIsDispatched($authenticationToken, $job->getId());
    }

    public function testDispatchNotReady(): void
    {
        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = $this->createDispatcher($readinessAssessor);

        $event = new JobCreatedEvent('api token', 'job id', 'suite id', []);

        $dispatcher->dispatchForJobCreatedEvent($event);

        $this->assertNoMessagesAreDispatched();
    }

    public function testRedispatchNeverReady(): void
    {
        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = $this->createDispatcher($readinessAssessor);

        $message = new CreateResultsJobMessage('api token', 'job id');
        $event = new MessageNotYetHandleableEvent($message);

        $dispatcher->redispatch($event);

        $this->assertNoMessagesAreDispatched();
    }

    #[DataProvider('redispatchSuccessDataProvider')]
    public function testRedispatchSuccess(MessageHandlingReadiness $readiness): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->andReturn($readiness)
        ;

        $dispatcher = $this->createDispatcher($readinessAssessor);

        $message = new CreateResultsJobMessage('api token', $job->getId());
        $event = new MessageNotYetHandleableEvent($message);

        $dispatcher->redispatch($event);

        $this->assertDelayedMessageIsDispatched($message->authenticationToken, $job->getId());
    }

    /**
     * @return array<mixed>
     */
    public static function redispatchSuccessDataProvider(): array
    {
        return [
            'ready now' => [
                'readiness' => MessageHandlingReadiness::NOW,
            ],
            'ready eventually' => [
                'readiness' => MessageHandlingReadiness::EVENTUALLY,
            ],
        ];
    }

    private function createDispatcher(ReadinessAssessorInterface $readinessAssessor): CreateResultsJobMessageDispatcher
    {
        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        return new CreateResultsJobMessageDispatcher($messageDispatcher, $readinessAssessor);
    }

    private function assertNoMessagesAreDispatched(): void
    {
        self::assertSame([], $this->messengerTransport->getSent());
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertNonDelayedMessageIsDispatched(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(new CreateResultsJobMessage($authenticationToken, $jobId), $envelope->getMessage());
        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDelayedMessageIsDispatched(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(new CreateResultsJobMessage($authenticationToken, $jobId), $envelope->getMessage());
        self::assertEquals([], $envelope->all(NonDelayedStamp::class));
    }
}
