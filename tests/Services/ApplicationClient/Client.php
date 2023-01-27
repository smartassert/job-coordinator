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

    /**
     * @param non-empty-string[] $manifestPaths
     */
    public function makeCreateJobRequest(
        ?string $authenticationToken,
        string $suiteId,
        array $manifestPaths,
        string $method = 'POST'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->router->generate('job_create', ['suiteId' => $suiteId]),
            array_merge(
                $this->createAuthorizationHeader($authenticationToken),
                [
                    'content-type' => 'application/json',
                ]
            ),
            (string) json_encode($manifestPaths)
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
