<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\HttpFactory;
use SmartAssert\ResultsClient\Client;
use SmartAssert\ResultsClient\EventFactory;
use SmartAssert\ResultsClient\ResourceReferenceFactory;
use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\ServiceClient\ExceptionFactory\CurlExceptionFactory;
use SmartAssert\ServiceClient\ResponseFactory\ResponseFactory;

class HttpMockedResultsClientFactory
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

        $eventFactory = new EventFactory(
            new ResourceReferenceFactory()
        );

        return new Client('null', $serviceClient, $eventFactory);
    }
}
