<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\MetaState;
use App\Repository\ResultsJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultsJobRepository::class)]
class ResultsJob implements \JsonSerializable
{
    #[ORM\Column(length: 32)]
    public readonly string $token;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private readonly string $jobId;

    #[ORM\Column(length: 128)]
    private string $state;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $endState;

    #[ORM\Column]
    private bool $stateIsEnded = false;

    #[ORM\Column]
    private bool $stateIsSucceeded = false;

    /**
     * @param non-empty-string  $jobId
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function __construct(
        string $jobId,
        string $token,
        string $state,
        ?string $endState,
        MetaState $metaState,
    ) {
        $this->jobId = $jobId;
        $this->token = $token;
        $this->state = $state;
        $this->endState = $endState;
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
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

    public function setMetaState(MetaState $metaState): self
    {
        $this->stateIsEnded = $metaState->ended;
        $this->stateIsSucceeded = $metaState->succeeded;

        return $this;
    }

    /**
     * @return array{
     *     'state': ?non-empty-string,
     *     'end_state': ?non-empty-string,
     *     'meta_state': MetaState,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => '' === $this->state ? null : $this->state,
            'end_state' => '' === $this->endState ? null : $this->endState,
            'meta_state' => new MetaState($this->stateIsEnded, $this->stateIsSucceeded),
        ];
    }
}
