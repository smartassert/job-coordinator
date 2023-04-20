<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\SourcesClient\Model\SerializedSuite as SourcesSerializedSuite;

class SerializedSuite implements \JsonSerializable
{
    public function __construct(
        private readonly SourcesSerializedSuite $sourcesSerializedSuite
    ) {
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   state: string,
     *   failure_reason?: string,
     *   failure_message?: string
     *  }
     */
    public function jsonSerialize(): array
    {
        $serializedSuiteData = [
            'id' => $this->sourcesSerializedSuite->getId(),
            'state' => $this->sourcesSerializedSuite->getState(),
        ];

        if (null !== $this->sourcesSerializedSuite->getFailureReason()) {
            $serializedSuiteData['failure_reason'] = $this->sourcesSerializedSuite->getFailureReason();
        }

        if (null !== $this->sourcesSerializedSuite->getFailureMessage()) {
            $serializedSuiteData['failure_message'] = $this->sourcesSerializedSuite->getFailureMessage();
        }

        return $serializedSuiteData;
    }
}
