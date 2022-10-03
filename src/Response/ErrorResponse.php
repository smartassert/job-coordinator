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
        string $message
    ) {
        parent::__construct(
            [
                'type' => $errorResponseType->value,
                'message' => $message,
            ],
            ErrorResponseType::SERVER_ERROR === $errorResponseType ? 500 : 400
        );
    }
}
