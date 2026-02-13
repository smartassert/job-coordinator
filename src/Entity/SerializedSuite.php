<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type SerializedSerializedSuite array{
 *   state: non-empty-string,
 *   is_prepared: bool,
 *   has_end_state: bool
 *  }
 */
#[ORM\Entity(repositoryClass: SerializedSuiteRepository::class)]
class SerializedSuite implements \JsonSerializable
{
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    public string $id;

    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $jobId;

    #[ORM\Column(length: 128, nullable: false)]
    private string $state;

    #[ORM\Column]
    private bool $stateIsEnded;

    #[ORM\Column]
    private bool $stateIsSucceeded;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function __construct(
        string $jobId,
        string $serializedSuiteId,
        string $state,
        MetaState $metaState,
    ) {
        $this->jobId = $jobId;
        $this->id = $serializedSuiteId;
        $this->state = $state;
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;
    }

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

    public function isPrepared(): bool
    {
        return $this->stateIsEnded && $this->stateIsSucceeded;
    }

    public function hasEndState(): bool
    {
        return $this->stateIsEnded;
    }

    public function isPreparing(): bool
    {
        return false === $this->isPrepared() && false === $this->hasFailed();
    }

    public function hasFailed(): bool
    {
        return true === $this->hasEndState() && false === $this->isPrepared();
    }

    /**
     * @return ?SerializedSerializedSuite
     */
    public function jsonSerialize(): ?array
    {
        if ('' === $this->state) {
            return null;
        }

        return [
            'state' => $this->state,
            'is_prepared' => $this->isPrepared(),
            'has_end_state' => $this->hasEndState(),
        ];
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
