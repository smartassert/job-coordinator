<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Model\JobInterface;

class StagingOutput
{
    /**
     * @var non-empty-string
     */
    private string $apiToken;

    private JobInterface $job;

    private SerializedSuite $serializedSuite;

    private Machine $machine;

    private ResultsJob $resultsJob;

    /**
     * @param non-empty-string $apiToken
     */
    public function __construct(
        string $apiToken,
        JobInterface $job,
        SerializedSuite $serializedSuite,
        Machine $machine,
        ResultsJob $resultsJob,
    ) {
        $this->apiToken = $apiToken;
        $this->job = $job;
        $this->serializedSuite = $serializedSuite;
        $this->machine = $machine;
        $this->resultsJob = $resultsJob;
    }

    /**
     * @return non-empty-string
     */
    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getJob(): JobInterface
    {
        return $this->job;
    }

    public function getSerializedSuite(): SerializedSuite
    {
        return $this->serializedSuite;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function getResultsJob(): ResultsJob
    {
        return $this->resultsJob;
    }
}
