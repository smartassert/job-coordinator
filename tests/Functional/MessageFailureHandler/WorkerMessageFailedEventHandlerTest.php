<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\MessageFailureHandler\ResultsJobCreationExceptionHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerMessageFailedEventBundle\HandlerFailedExceptionHandler;
use SmartAssert\WorkerMessageFailedEventBundle\WorkerMessageFailedEventHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerMessageFailedEventHandlerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private WorkerMessageFailedEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(WorkerMessageFailedEventHandler::class);
        \assert($handler instanceof WorkerMessageFailedEventHandler);
        $this->handler = $handler;
    }

    /**
     * @dataProvider hasExpectedWorkerMessageFailedEventHandlerHandlersDataProvider
     */
    public function testHasExpectedWorkerMessageFailedEventHandlerHandlers(string $expectedHandlerClass): void
    {
        $handlerFound = false;
        foreach ($this->handler->handlers as $handler) {
            if ($handler instanceof $expectedHandlerClass) {
                $handlerFound = true;
            }
        }

        self::assertTrue($handlerFound, sprintf('Handler "%s" not found.', $expectedHandlerClass));
    }

    /**
     * @return array<mixed>
     */
    public function hasExpectedWorkerMessageFailedEventHandlerHandlersDataProvider(): array
    {
        return [
            HandlerFailedExceptionHandler::class => [
                'expectedHandlerClass' => HandlerFailedExceptionHandler::class,
            ],
        ];
    }

    /**
     * @dataProvider hasExpectedHandlerFailedExceptionHandlerHandlersDataProvider
     */
    public function testHasExpectedHandlerFailedExceptionHandlerHandlers(string $expectedHandlerClass): void
    {
        $handlerFailedExceptionHandler = null;

        foreach ($this->handler->handlers as $handler) {
            if ($handler instanceof HandlerFailedExceptionHandler) {
                $handlerFailedExceptionHandler = $handler;
            }
        }

        self::assertInstanceOf(HandlerFailedExceptionHandler::class, $handlerFailedExceptionHandler);

        $handlerFound = false;
        foreach ($handlerFailedExceptionHandler->handlers as $handler) {
            if ($handler instanceof $expectedHandlerClass) {
                $handlerFound = true;
            }
        }

        self::assertTrue($handlerFound, sprintf('Handler "%s" not found.', $expectedHandlerClass));
    }

    /**
     * @return array<mixed>
     */
    public function hasExpectedHandlerFailedExceptionHandlerHandlersDataProvider(): array
    {
        return [
            ResultsJobCreationExceptionHandler::class => [
                'expectedHandlerClass' => ResultsJobCreationExceptionHandler::class,
            ],
        ];
    }
}
