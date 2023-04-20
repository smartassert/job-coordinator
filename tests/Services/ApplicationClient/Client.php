<?php

declare(strict_types=1);

namespace App\Tests\Services\ApplicationClient;

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
        string $method = 'POST'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->router->generate('job_create', ['suiteId' => $suiteId]),
            $this->createAuthorizationHeader($authenticationToken)
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
