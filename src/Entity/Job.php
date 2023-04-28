<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
class Job implements \JsonSerializable
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
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $resultsToken;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $serializedSuiteId;

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
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $id
     * @param non-empty-string $resultsToken
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        string $id,
        string $userId,
        string $suiteId,
        string $resultsToken,
        string $serializedSuiteId
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->resultsToken = $resultsToken;
        $this->serializedSuiteId = $serializedSuiteId;
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
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   serialized_suite_id: non-empty-string
     *  }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
            'serialized_suite_id' => $this->serializedSuiteId,
        ];
    }
}
