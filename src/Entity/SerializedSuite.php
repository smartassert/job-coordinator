<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SerializedSuiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SerializedSuiteRepository::class)]
class SerializedSuite implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $jobId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $serializedSuiteId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 128, nullable: false)]
    private string $state;

    #[ORM\Column(nullable: false)]
    private bool $isPrepared;

    #[ORM\Column(nullable: false)]
    private bool $hasEndState;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function __construct(
        string $jobId,
        string $serializedSuiteId,
        string $state,
        bool $isPrepared,
        bool $hasEndState,
    ) {
        $this->jobId = $jobId;
        $this->serializedSuiteId = $serializedSuiteId;
        $this->state = $state;
        $this->isPrepared = $isPrepared;
        $this->hasEndState = $hasEndState;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->serializedSuiteId;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function setIsPrepared(bool $isPrepared): static
    {
        $this->isPrepared = $isPrepared;

        return $this;
    }

    public function isPrepared(): bool
    {
        return $this->isPrepared;
    }

    public function isPreparing(): bool
    {
        return false === $this->isPrepared && false === $this->hasFailed();
    }

    public function hasFailed(): bool
    {
        return true === $this->hasEndState && false === $this->isPrepared;
    }

    public function setHasEndState(bool $hasEndState): static
    {
        $this->hasEndState = $hasEndState;

        return $this;
    }

    public function hasEndState(): bool
    {
        return $this->hasEndState;
    }

    /**
     * @return array{state: ?non-empty-string}
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'is_prepared' => $this->isPrepared,
            'has_end_state' => $this->hasEndState,
        ];
    }
}
