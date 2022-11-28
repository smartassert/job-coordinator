<?php

namespace App\Services;

use App\Enum\ErrorResponseType;
use App\Response\ErrorResponse;
use SmartAssert\ServiceClient\Exception\HttpResponseExceptionInterface;
use SmartAssert\ServiceClient\Exception\HttpResponsePayloadExceptionInterface;

class ErrorResponseFactory
{
    /**
     * @param non-empty-string $responseMessage
     */
    public function createFromHttpResponseException(
        HttpResponseExceptionInterface $exception,
        string $responseMessage,
    ): ErrorResponse {
        $httpResponse = $exception->getResponse();

        $responseData = $exception instanceof HttpResponsePayloadExceptionInterface
            ? $exception->getPayload()
            : $httpResponse->getBody()->getContents();

        if ('' === $responseData) {
            $responseData = $httpResponse->getReasonPhrase();
        }

        return new ErrorResponse(
            ErrorResponseType::SERVER_ERROR,
            $responseMessage,
            [
                'service_response' => [
                    'status_code' => $exception->getResponse()->getStatusCode(),
                    'content_type' => $exception->getResponse()->getHeaderLine('content-type'),
                    'data' => $responseData,
                ],
            ],
        );
    }
}
