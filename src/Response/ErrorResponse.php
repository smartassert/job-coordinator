<?php

namespace App\Response;

use App\Enum\ErrorResponseType;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponse extends JsonResponse
{
    /**
     * @param non-empty-string $message
     */
    public function __construct(
        ErrorResponseType $errorResponseType,
        string $field,
        null|int|string $actual,
        string $message
    ) {
        parent::__construct(
            [
                'error' => [
                    'type' => $errorResponseType->value,
                    'payload' => [
                        $field => [
                            'value' => $actual,
                            'message' => $message,
                        ],
                    ],
                ],
            ],
            ErrorResponseType::SERVER_ERROR === $errorResponseType ? 500 : 400
        );
    }
}
