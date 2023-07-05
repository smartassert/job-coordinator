<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkerStateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerStateRepository::class)]
class WorkerState
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private readonly string $jobId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $applicationState;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $compilationState;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $executionState;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $eventDeliveryState;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $applicationState
     * @param non-empty-string $compilationState
     * @param non-empty-string $executionState
     * @param non-empty-string $eventDeliveryState
     */
    public function __construct(
        string $jobId,
        string $applicationState,
        string $compilationState,
        string $executionState,
        string $eventDeliveryState,
    ) {
        $this->jobId = $jobId;
        $this->applicationState = $applicationState;
        $this->compilationState = $compilationState;
        $this->executionState = $executionState;
        $this->eventDeliveryState = $eventDeliveryState;
    }

    /**
     * @return non-empty-string
     */
    public function getApplicationState(): string
    {
        return $this->applicationState;
    }

    /**
     * @param non-empty-string $state
     */
    public function setApplicationState(string $state): static
    {
        $this->applicationState = $state;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getCompilationState(): string
    {
        return $this->compilationState;
    }

    /**
     * @param non-empty-string $state
     */
    public function setCompilationState(string $state): static
    {
        $this->compilationState = $state;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getExecutionState(): string
    {
        return $this->executionState;
    }

    /**
     * @param non-empty-string $state
     */
    public function setExecutionState(string $state): static
    {
        $this->executionState = $state;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getEventDeliveryState(): string
    {
        return $this->eventDeliveryState;
    }

    /**
     * @param non-empty-string $state
     */
    public function setEventDeliveryState(string $state): static
    {
        $this->eventDeliveryState = $state;

        return $this;
    }
}
