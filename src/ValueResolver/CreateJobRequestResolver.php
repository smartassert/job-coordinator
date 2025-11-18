<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Request\CreateJobRequest;
use SmartAssert\ServiceRequest\Exception\ErrorResponseException;
use SmartAssert\ServiceRequest\Parameter\Parameter;
use SmartAssert\ServiceRequest\Parameter\Requirements;
use SmartAssert\ServiceRequest\Parameter\Size;
use SmartAssert\ServiceRequest\Parameter\Validator\PositiveIntegerParameterValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class CreateJobRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private PositiveIntegerParameterValidator $parameterValidator,
    ) {}

    /**
     * @return CreateJobRequest[]
     *
     * @throws ErrorResponseException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (CreateJobRequest::class !== $argument->getType()) {
            return [];
        }

        $suiteId = $request->attributes->get('suiteId');
        if (!is_string($suiteId) || '' === $suiteId) {
            return [];
        }

        $parameter = (new Parameter(
            CreateJobRequest::KEY_MAXIMUM_DURATION_IN_SECONDS,
            $request->request->getInt(CreateJobRequest::KEY_MAXIMUM_DURATION_IN_SECONDS)
        ))->withRequirements(
            new Requirements(
                'integer',
                new Size(1, CreateJobRequest::MAXIMUM_DURATION_IN_SECONDS_MAX_SIZE)
            )
        );

        return [new CreateJobRequest(
            $suiteId,
            $this->parameterValidator->validateInteger($parameter),
            $this->getJobParameters($request->request->all(CreateJobRequest::KEY_PARAMETERS))
        )];
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
}
