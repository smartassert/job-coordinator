<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResultsJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultsJobRepository::class)]
class ResultsJob
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    public readonly string $id;

    #[ORM\Column(length: 32)]
    public readonly string $token;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 128)]
    private string $state;

    /**
     * @var ?non-empty-string
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $endState;

    /**
     * @param non-empty-string  $id
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function __construct(string $id, string $token, string $state, ?string $endState)
    {
        $this->id = $id;
        $this->token = $token;
        $this->state = $state;
        $this->endState = $endState;
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
    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return ?non-empty-string
     */
    public function getEndState(): ?string
    {
        return $this->state;
    }

    /**
     * @param non-empty-string $state
     */
    public function setEndState(string $state): self
    {
        $this->endState = $state;

        return $this;
    }

    /**
     * @return array{has_token: bool, state: ?non-empty-string, end_state: ?non-empty-string}
     */
    public function toArray(): array
    {
        return [
            'has_token' => is_string($this->token),
            'state' => $this->state,
            'end_state' => $this->endState,
        ];
    }
}
