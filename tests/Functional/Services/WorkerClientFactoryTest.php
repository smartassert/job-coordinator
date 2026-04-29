<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\WorkerClientFactory;
use GuzzleHttp\Client as GuzzleHttpClient;
use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\WorkerClient\Client as WorkerClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerClientFactoryTest extends WebTestCase
{
    private WorkerClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(WorkerClientFactory::class);
        \assert($factory instanceof WorkerClientFactory);

        $this->client = $factory->create('127.0.0.1');
    }

    public function testWorkerClientHttpClientSupportsInsecureHttps(): void
    {
        $workerClientReflector = new \ReflectionObject($this->client);

        $workerClientServiceClientProperty = $workerClientReflector->getProperty('serviceClient');
        $workerClientServiceClient = $workerClientServiceClientProperty->getValue($this->client);
        \assert($workerClientServiceClient instanceof ServiceClient);

        $serviceClientReflector = new \ReflectionObject($workerClientServiceClient);

        $serviceClientHttpClientProperty = $serviceClientReflector->getProperty('httpClient');
        $serviceClientHttpClient = $serviceClientHttpClientProperty->getValue($workerClientServiceClient);
        \assert($serviceClientHttpClient instanceof GuzzleHttpClient);

        $httpClientReflector = new \ReflectionObject($serviceClientHttpClient);

        $httpClientConfigProperty = $httpClientReflector->getProperty('config');
        $httpClientConfig = $httpClientConfigProperty->getValue($serviceClientHttpClient);
        \assert(is_array($httpClientConfig));

        self::assertFalse($httpClientConfig['verify']);
    }

    public function testWorkerClientBaseUrlIsHttps(): void
    {
        $workerClientReflector = new \ReflectionObject($this->client);

        $workerClientBaseUrlProperty = $workerClientReflector->getProperty('baseUrl');
        $workerClientBaseUrl = $workerClientBaseUrlProperty->getValue($this->client);
        \assert(is_string($workerClientBaseUrl));

        self::assertStringStartsWith('https://', $workerClientBaseUrl);
    }
}
