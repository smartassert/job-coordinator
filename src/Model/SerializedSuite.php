<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedSerializedSuite array{
 *   state: non-empty-string,
 *   is_prepared: bool,
 *   has_end_state: bool
 *  }
 */
readonly class SerializedSuite implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    private string $serializedSuiteId;

    /**
     * @var non-empty-string
     */
    private string $state;
    private bool $isPrepared;
    private bool $hasEndState;

    /**
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function __construct(
        string $serializedSuiteId,
        string $state,
        bool $isPrepared,
        bool $hasEndState,
    ) {
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

    public function hasEndState(): bool
    {
        return $this->hasEndState;
    }

    /**
     * @return SerializedSerializedSuite
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
