<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\HttpFactory;
use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\ServiceClient\ExceptionFactory\CurlExceptionFactory;
use SmartAssert\ServiceClient\ResponseFactory\ResponseFactory;
use SmartAssert\WorkerClient\Client;
use SmartAssert\WorkerClient\Factory\ApplicationStateFactory;
use SmartAssert\WorkerClient\Factory\ComponentMetaStateFactory;
use SmartAssert\WorkerClient\Factory\ComponentStateFactory;
use SmartAssert\WorkerClient\Factory\EventFactory;
use SmartAssert\WorkerClient\Factory\JobCreationExceptionFactory;
use SmartAssert\WorkerClient\Factory\JobFactory;
use SmartAssert\WorkerClient\Factory\ResourceReferenceFactory;
use SmartAssert\WorkerClient\Factory\TestFactory;

class HttpMockedWorkerClientFactory
{
    /**
     * @param array<mixed> $httpFixtures
     */
    public static function create(array $httpFixtures = []): Client
    {
        $httpClient = new HttpClient([
            'handler' => new MockHandler($httpFixtures),
        ]);

        $httpFactory = new HttpFactory();

        $serviceClient = new ServiceClient(
            $httpFactory,
            $httpFactory,
            $httpClient,
            ResponseFactory::createFactory(),
            new CurlExceptionFactory()
        );

        $resourceReferenceFactory = new ResourceReferenceFactory();
        $eventFactory = new EventFactory($resourceReferenceFactory);
        $jobFactory = new JobFactory($resourceReferenceFactory, new TestFactory());
        $applicationStateFactory = new ApplicationStateFactory(
            new ComponentStateFactory(
                new ComponentMetaStateFactory(),
            ),
        );
        $jobCreationExceptionFactory = new JobCreationExceptionFactory();

        return new Client(
            'null',
            $serviceClient,
            $eventFactory,
            $jobFactory,
            $applicationStateFactory,
            $jobCreationExceptionFactory,
        );
    }
}
