<?php

declare(strict_types=1);

namespace App\Response;

use App\Enum\ErrorResponseType;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponse extends JsonResponse
{
    /**
     * @param non-empty-string $message
     * @param array<mixed>     $context
     */
    public function __construct(
        ErrorResponseType $errorResponseType,
        string $message,
        ?array $context = null,
    ) {
        $payload = [
            'type' => $errorResponseType->value,
            'message' => $message,
        ];

        if (null !== $context) {
            $payload['context'] = $context;
        }

        parent::__construct($payload, ErrorResponseType::SERVER_ERROR === $errorResponseType ? 500 : 400);
    }
}
