<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\WorkerClientFactory;
use GuzzleHttp\Client as GuzzleHttpClient;
use SmartAssert\ServiceClient\Client as ServiceClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerClientFactoryTest extends WebTestCase
{
    public function testFoo(): void
    {
        $factory = self::getContainer()->get(WorkerClientFactory::class);
        \assert($factory instanceof WorkerClientFactory);

        $workerClient = $factory->create('https://example.com');

        $workerClientReflector = new \ReflectionObject($workerClient);

        $workerClientServiceClientProperty = $workerClientReflector->getProperty('serviceClient');
        $workerClientServiceClient = $workerClientServiceClientProperty->getValue($workerClient);
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
}
