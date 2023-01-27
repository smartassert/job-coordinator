<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\CreateJobRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CreateJobRequestResolver implements ValueResolverInterface
{
    /**
     * @return CreateJobRequest[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (CreateJobRequest::class !== $argument->getType()) {
            return [];
        }

        $requestSuiteId = $request->attributes->get('suiteId');
        if (!is_string($requestSuiteId)) {
            return [];
        }

        $suiteId = trim($requestSuiteId);
        if ('' === $suiteId) {
            return [];
        }

        if ('application/json' !== $request->headers->get('content-type')) {
            return [];
        }

        $requestManifestPaths = json_decode($request->getContent(), true);
        if (!is_array($requestManifestPaths)) {
            return [];
        }

        $manifestPaths = [];
        foreach ($requestManifestPaths as $requestManifestPath) {
            if (is_string($requestManifestPath)) {
                $comparator = trim($requestManifestPath);

                if ('' !== $comparator) {
                    $manifestPaths[] = $comparator;
                }
            }
        }

        return [new CreateJobRequest($suiteId, $manifestPaths)];
    }
}
