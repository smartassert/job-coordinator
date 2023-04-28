<?php

declare(strict_types=1);

namespace App\Exception;

class SerializedSuiteRetrievalException extends \Exception
{
    public function __construct(
        public readonly string $serializedSuiteId,
        public readonly \Throwable $previousException
    ) {
        parent::__construct(
            sprintf(
                'Failed to retrieve serialized suite "%s": %s',
                $this->serializedSuiteId,
                $this->previousException->getMessage()
            ),
            0,
            $previousException
        );
    }
}
