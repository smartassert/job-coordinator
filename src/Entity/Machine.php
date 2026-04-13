<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\MetaState;
use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine
{
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private readonly string $jobId;

    #[ORM\Column(length: 128, nullable: false)]
    private string $state;

    #[ORM\Column(length: 128, nullable: false)]
    private string $stateCategory;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?MachineActionFailure $actionFailure = null;

    #[ORM\Column]
    private bool $stateIsEnded;

    #[ORM\Column]
    private bool $stateIsSucceeded;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $state
     * @param non-empty-string $stateCategory
     */
    public function __construct(
        string $jobId,
        string $state,
        string $stateCategory,
        MetaState $metaState,
    ) {
        $this->jobId = $jobId;
        $this->state = $state;
        $this->stateCategory = $stateCategory;
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;
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
     * @return ?non-empty-string
     */
    public function getStateCategory(): ?string
    {
        return '' === $this->stateCategory ? null : $this->stateCategory;
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
        return '' === $this->ip ? null : $this->ip;
    }

    /**
     * @param non-empty-string $ip
     */
    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getActionFailure(): ?MachineActionFailure
    {
        return $this->actionFailure;
    }

    public function setActionFailure(?MachineActionFailure $actionFailure): static
    {
        $this->actionFailure = $actionFailure;

        return $this;
    }

    public function setMetaState(MetaState $metaState): self
    {
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;

        return $this;
    }

    public function getMetaState(): MetaState
    {
        return new MetaState($this->stateIsEnded, $this->stateIsSucceeded);
    }
}
