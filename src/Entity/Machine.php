<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\MachineInterface;
use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine implements MachineInterface
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private readonly string $jobId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 128, nullable: false)]
    private string $state;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 128, nullable: false)]
    private string $stateCategory;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ip = null;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $state
     * @param non-empty-string $stateCategory
     */
    public function __construct(string $jobId, string $state, string $stateCategory)
    {
        $this->jobId = $jobId;
        $this->state = $state;
        $this->stateCategory = $stateCategory;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->jobId;
    }

    /**
     * @return non-empty-string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getStateCategory(): string
    {
        return $this->stateCategory;
    }

    /**
     * @param non-empty-string $stateCategory
     */
    public function setStateCategory(string $stateCategory): static
    {
        $this->stateCategory = $stateCategory;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @param non-empty-string $ip
     */
    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }
}
