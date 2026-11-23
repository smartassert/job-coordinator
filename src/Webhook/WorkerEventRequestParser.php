<?php

declare(strict_types=1);

namespace App\Webhook;

use App\Repository\JobRepository;
use App\Request\WorkerApplicationStateChange\Factory;
use SmartAssert\WorkerClient\Factory\ApplicationStateFactory;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class WorkerEventRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly Factory $requestFactory,
        private readonly ApplicationStateFactory $applicationStateFactory,
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
    ) {}

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $body = $request->toArray();
        $applicationStateChangeRequest = $this->requestFactory->create($body);

        $job = $this->jobRepository->find($applicationStateChangeRequest->jobId);
        if (null === $job) {
            throw new RejectWebhookException(
                404,
                \sprintf('Job "%s" not found.', $applicationStateChangeRequest->jobId),
            );
        }

        foreach ([$this->signatureHeaderName, $this->eventHeaderName, $this->idHeaderName] as $header) {
            if (!$request->headers->has($header)) {
                throw new RejectWebhookException(406, \sprintf('Missing "%s" HTTP request signature header.', $header));
            }
        }

        $this->validateSignature($request->headers, $request->getContent(), $job->getNotifyToken());

        $applicationState = $this->applicationStateFactory->create($applicationStateChangeRequest->stateData);

        if (null === $applicationState) {
            throw new RejectWebhookException(400, 'State data invalid.');
        }

        return new RemoteEvent(
            (string) $request->headers->get($this->eventHeaderName),
            (string) $request->headers->get($this->idHeaderName),
            $body
        );
    }

    private function validateSignature(HeaderBag $headers, string $body, #[\SensitiveParameter] string $secret): void
    {
        $signature = (string) $headers->get($this->signatureHeaderName);
        $event = (string) $headers->get($this->eventHeaderName);
        $id = (string) $headers->get($this->idHeaderName);

        if (!hash_equals($signature, $this->algo . '=' . hash_hmac($this->algo, $event . $id . $body, $secret))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
