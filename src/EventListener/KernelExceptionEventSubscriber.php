<?php

declare(strict_types=1);

namespace App\EventListener;

use SmartAssert\ServiceRequest\Exception\ErrorResponseException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class KernelExceptionEventSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => [
                ['onKernelException', 100],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $response = null;

        if ($throwable instanceof AccessDeniedException) {
            $response = new Response(null, 401);
        }

        if ($throwable instanceof ErrorResponseException) {
            $response = new JsonResponse($throwable->error->serialize(), $throwable->getCode());
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $contentType = $throwable->getHeaders()['content-type'] ?? null;

            if ('application/json' == $contentType) {
                $response = new JsonResponse(
                    $throwable->getMessage(),
                    $throwable->getStatusCode(),
                    ['content-type' => $contentType],
                    true
                );
            }
        }

        if ($response instanceof Response) {
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
