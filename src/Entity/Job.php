<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
class Job
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    public readonly string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $userId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $suiteId;

    /**
     * @var positive-int
     */
    #[ORM\Column]
    public readonly int $maximumDurationInSeconds;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $serializedSuiteId = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $machineIpAddress = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $serializedSuiteState = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $machineStateCategory = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $resultsJobState = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $resultsJobEndState = null;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $id
     * @param positive-int     $maximumDurationInSeconds
     */
    public function __construct(
        string $id,
        string $userId,
        string $suiteId,
        int $maximumDurationInSeconds
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
    }

    /**
     * @param non-empty-string $serializedSuiteId
     */
    public function setSerializedSuiteId(string $serializedSuiteId): self
    {
        $this->serializedSuiteId = $serializedSuiteId;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getSerializedSuiteId(): ?string
    {
        return $this->serializedSuiteId;
    }

    /**
     * @param non-empty-string $machineIpAddress
     */
    public function setMachineIpAddress(string $machineIpAddress): self
    {
        $this->machineIpAddress = $machineIpAddress;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getMachineIpAddress(): ?string
    {
        return $this->machineIpAddress;
    }

    /**
     * @param non-empty-string $serializedSuiteState
     */
    public function setSerializedSuiteState(string $serializedSuiteState): self
    {
        $this->serializedSuiteState = $serializedSuiteState;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getSerializedSuiteState(): ?string
    {
        return $this->serializedSuiteState;
    }

    /**
     * @param non-empty-string $machineStateCategory
     */
    public function setMachineStateCategory(string $machineStateCategory): self
    {
        $this->machineStateCategory = $machineStateCategory;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getMachineStateCategory(): ?string
    {
        return $this->machineStateCategory;
    }

    /**
     * @param non-empty-string $state
     */
    public function setResultsJobEndState(string $state): self
    {
        $this->resultsJobEndState = $state;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getResultsJobEndState(): ?string
    {
        return $this->resultsJobEndState;
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   maximum_duration_in_seconds: positive-int,
     *   serialized_suite: array{id: ?non-empty-string, state: ?non-empty-string},
     *   machine: array{state_category: ?non-empty-string, ip_address: ?non-empty-string},
     *   results_job: array{state: ?non-empty-string, end_state: ?non-empty-string}
     *  }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
            'serialized_suite' => [
                'id' => $this->serializedSuiteId,
                'state' => $this->serializedSuiteState,
            ],
            'machine' => [
                'state_category' => $this->machineStateCategory,
                'ip_address' => $this->machineIpAddress,
            ],
            'results_job' => [
                'state' => $this->resultsJobState,
                'end_state' => $this->resultsJobEndState,
            ],
        ];
    }
}
