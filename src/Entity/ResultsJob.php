<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResultsJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultsJobRepository::class)]
class ResultsJob implements \JsonSerializable
{
    #[ORM\Column(length: 32)]
    public readonly string $token;

    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private readonly string $jobId;

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
     * @param non-empty-string  $jobId
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function __construct(string $jobId, string $token, string $state, ?string $endState)
    {
        $this->jobId = $jobId;
        $this->token = $token;
        $this->state = $state;
        $this->endState = $endState;
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

    /**
     * @return array{state: ?non-empty-string,  end_state: ?non-empty-string}
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state ?? null,
            'end_state' => $this->endState ?? null,
        ];
    }
}
