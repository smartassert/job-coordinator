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
    #[ORM\Column(length: 32)]
    private readonly string $jobId;

    #[ORM\Id]
    #[ORM\Column(length: 64, nullable: false, enumType: WorkerComponentName::class)]
    private readonly WorkerComponentName $componentName;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 64)]
    private string $state;

    #[ORM\Column]
    private bool $isEndState;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(string $jobId, WorkerComponentName $componentName)
    {
        $this->jobId = $jobId;
        $this->componentName = $componentName;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
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
