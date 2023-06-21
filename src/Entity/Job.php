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
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $machineIpAddress = null;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $machineStateCategory = null;

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
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   maximum_duration_in_seconds: positive-int
     *  }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
        ];
    }
}
