<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Controller\JobRoutes;
use App\Request\CreateJobRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateJobRequestResolver implements ValueResolverInterface
{
    /**
     * @return CreateJobRequest[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (CreateJobRequest::class !== $argument->getType()) {
            return [];
        }

        $suiteId = $request->attributes->get(JobRoutes::SUITE_ID_ATTRIBUTE);
        if (!is_string($suiteId) || '' === $suiteId) {
            return [];
        }

        $payload = $this->getDecodedRequestPayload($request);
        $maximumDurationInSeconds = $payload[CreateJobRequest::KEY_MAXIMUM_DURATION_IN_SECONDS] ?? null;
        if (
            !is_int($maximumDurationInSeconds)
            || $maximumDurationInSeconds < 1
            || $maximumDurationInSeconds > CreateJobRequest::MAXIMUM_DURATION_IN_SECONDS_MAX_SIZE
        ) {
            throw new BadRequestHttpException(
                message: (string) json_encode([
                    'error' => [
                        'type' => 'invalid_request',
                        'payload' => [
                            'name' => 'maximum_duration_in_seconds',
                            'value' => is_scalar($maximumDurationInSeconds) ? $maximumDurationInSeconds : '',
                            'message' => sprintf(
                                'Maximum duration in seconds must be an integer between 1 and %d',
                                CreateJobRequest::MAXIMUM_DURATION_IN_SECONDS_MAX_SIZE
                            ),
                        ],
                    ],
                ]),
                headers: ['content-type' => 'application/json']
            );
        }

        return [new CreateJobRequest($suiteId, $maximumDurationInSeconds, $this->getJobParameters($payload))];
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private function getJobParameters(array $payload): array
    {
        $payloadParameters = $payload['parameters'] ?? [];
        if (!is_array($payloadParameters)) {
            $payloadParameters = [];
        }

        $parameters = [];
        foreach ($payloadParameters as $key => $value) {
            if (is_string($key) && '' !== $key && is_string($value) && '' !== $value) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * @return array<mixed>
     */
    private function getDecodedRequestPayload(Request $request): array
    {
        if ('json' !== $request->getContentTypeFormat()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }
}
