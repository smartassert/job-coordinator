<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerComponentName;
use App\Model\WorkerComponentStateInterface;
use App\Repository\WorkerComponentStateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentStateInterface
 */
#[ORM\Entity(repositoryClass: WorkerComponentStateRepository::class)]
class WorkerComponentState implements WorkerComponentStateInterface
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $state;

    #[ORM\Column]
    private bool $isEndState;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $state
     */
    public function __construct(
        string $jobId,
        WorkerComponentName $componentName,
        string $state,
        bool $isEndState,
    ) {
        $this->id = static::generateId($jobId, $componentName);
        $this->state = $state;
        $this->isEndState = $isEndState;
    }

    /**
     * @param non-empty-string $jobId
     *
     * @return non-empty-string
     */
    public static function generateId(string $jobId, WorkerComponentName $componentName): string
    {
        return md5($jobId . $componentName->value);
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

    public function getIsEndState(): bool
    {
        return $this->isEndState;
    }

    public function setIsEndState(bool $isEndState): static
    {
        $this->isEndState = $isEndState;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'is_end_state' => $this->isEndState,
        ];
    }
}
