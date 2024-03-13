<?php

declare(strict_types=1);

namespace App\Tests\Services\ApplicationClient;

use App\Request\CreateJobRequest;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;
use Symfony\Component\Routing\RouterInterface;

class Client
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RouterInterface $router,
    ) {
    }

    public function makeCreateJobRequest(
        ?string $authenticationToken,
        string $suiteId,
        ?int $maximumDurationInSeconds,
        string $method = 'POST'
    ): ResponseInterface {
        $requestPayload = [];
        if (is_int($maximumDurationInSeconds)) {
            $requestPayload[CreateJobRequest::KEY_MAXIMUM_DURATION_IN_SECONDS] = $maximumDurationInSeconds;
        }

        return $this->client->makeRequest(
            $method,
            $this->router->generate('job_create', ['suiteId' => $suiteId]),
            array_merge(
                $this->createAuthorizationHeader($authenticationToken),
                ['content-type' => 'application/x-www-form-urlencoded'],
            ),
            http_build_query($requestPayload)
        );
    }

    public function makeGetJobRequest(
        ?string $authenticationToken,
        string $jobId,
        string $method = 'GET'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->router->generate('job_get', ['jobId' => $jobId]),
            $this->createAuthorizationHeader($authenticationToken)
        );
    }

    public function makeHealthCheckRequest(string $method = 'GET'): ResponseInterface
    {
        return $this->client->makeRequest($method, $this->router->generate('health-check'));
    }

    public function makeStatusRequest(string $method = 'GET'): ResponseInterface
    {
        return $this->client->makeRequest($method, $this->router->generate('status'));
    }

    /**
     * @return array<string, string>
     */
    private function createAuthorizationHeader(?string $authenticationToken): array
    {
        $headers = [];
        if (is_string($authenticationToken)) {
            $headers = [
                'authorization' => 'Bearer ' . $authenticationToken,
            ];
        }

        return $headers;
    }
}
