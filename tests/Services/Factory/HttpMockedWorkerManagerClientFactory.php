<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\HttpFactory;
use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\ServiceClient\ExceptionFactory\CurlExceptionFactory;
use SmartAssert\ServiceClient\ResponseFactory\ResponseFactory;
use SmartAssert\WorkerManagerClient\Client;
use SmartAssert\WorkerManagerClient\Factory\ActionFailureFactory;
use SmartAssert\WorkerManagerClient\Factory\MachineFactory;
use SmartAssert\WorkerManagerClient\RequestFactory;

class HttpMockedWorkerManagerClientFactory
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

        return new Client(
            $serviceClient,
            new RequestFactory('null'),
            new MachineFactory(
                new ActionFailureFactory(),
            ),
        );
    }
}
