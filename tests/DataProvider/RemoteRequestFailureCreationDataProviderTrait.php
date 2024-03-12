<?php

declare(strict_types=1);

namespace App\Tests\DataProvider;

use App\Enum\RemoteRequestFailureType;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SmartAssert\ServiceClient\Exception\CurlException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\ServiceClient\Response\Response as ServiceClientResponse;

trait RemoteRequestFailureCreationDataProviderTrait
{
    /**
     * @return array<class-string, array{
     *     throwable: \Throwable,
     *     expectedType: RemoteRequestFailureType,
     *     expectedCode: int,
     *     expectedMessage: string
     * }>
     */
    public function remoteRequestFailureCreationDataProvider(): array
    {
        $request = \Mockery::mock(RequestInterface::class);

        return [
            CurlException::class => [
                'throwable' => new CurlException($request, 28, 'timed out'),
                'expectedType' => RemoteRequestFailureType::NETWORK,
                'expectedCode' => 28,
                'expectedMessage' => 'timed out',
            ],
            NonSuccessResponseException::class => [
                'throwable' => new NonSuccessResponseException(
                    new ServiceClientResponse(
                        new Response(status: 503, reason: 'service unavailable')
                    ),
                ),
                'expectedType' => RemoteRequestFailureType::HTTP,
                'expectedCode' => 503,
                'expectedMessage' => 'service unavailable',
            ],
            ConnectException::class => [
                'throwable' => new ConnectException('network exception message', $request),
                'expectedType' => RemoteRequestFailureType::NETWORK,
                'expectedCode' => 0,
                'expectedMessage' => 'network exception message',
            ],
            \Exception::class => [
                'throwable' => new \Exception('generic exception message', 123),
                'expectedType' => RemoteRequestFailureType::UNKNOWN,
                'expectedCode' => 123,
                'expectedMessage' => 'generic exception message',
            ],
        ];
    }
}
