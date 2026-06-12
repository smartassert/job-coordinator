<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\MetaState;
use App\Repository\ResultsJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultsJobRepository::class)]
class ResultsJob
{
    #[ORM\Column(length: 255)]
    public readonly string $eventAddUrl;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private readonly string $jobId;

    #[ORM\Column(length: 128)]
    private string $state;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $endState;

    #[ORM\Column]
    private bool $stateIsEnded;

    #[ORM\Column]
    private bool $stateIsSucceeded;

    #[ORM\Column]
    private bool $isPending;

    #[ORM\Column]
    private bool $hasEvents;

    /**
     * @param non-empty-string  $jobId
     * @param non-empty-string  $eventAddUrl
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function __construct(
        string $jobId,
        string $eventAddUrl,
        string $state,
        ?string $endState,
        MetaState $metaState,
    ) {
        $this->jobId = $jobId;
        $this->eventAddUrl = $eventAddUrl;
        $this->state = $state;
        $this->endState = $endState;
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;
        $this->isPending = $metaState->pending;
        $this->hasEvents = false;
    }

    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getEndState(): ?string
    {
        return $this->endState;
    }

    public function hasEndState(): bool
    {
        return null !== $this->endState;
    }

    /**
     * @param non-empty-string $state
     */
    public function setEndState(string $state): self
    {
        $this->endState = $state;

        return $this;
    }

    public function getMetaState(): MetaState
    {
        return new MetaState($this->stateIsEnded, $this->stateIsSucceeded, $this->isPending);
    }

    public function setMetaState(MetaState $metaState): self
    {
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;
        $this->isPending = $metaState->pending;

        return $this;
    }

    public function setHasEvents(): self
    {
        $this->hasEvents = true;

        return $this;
    }

    public function hasEvents(): bool
    {
        return $this->hasEvents;
    }
}
