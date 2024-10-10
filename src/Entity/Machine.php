<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine implements \JsonSerializable
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

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?MachineActionFailure $actionFailure = null;

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

    public function getActionFailure(): ?MachineActionFailure
    {
        return $this->actionFailure;
    }

    public function setActionFailure(?MachineActionFailure $actionFailure): static
    {
        $this->actionFailure = $actionFailure;

        return $this;
    }

    /**
     * @return array{
     *   state_category: non-empty-string,
     *   ip_address: ?non-empty-string,
     *   action_failure: ?MachineActionFailure
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'state_category' => $this->stateCategory,
            'ip_address' => $this->ip,
            'action_failure' => $this->actionFailure,
        ];
    }
}
