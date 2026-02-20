<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerComponentName;
use App\Model\MetaState;
use App\Model\WorkerComponentStateInterface;
use App\Repository\WorkerComponentStateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentStateInterface
 */
#[ORM\Entity(repositoryClass: WorkerComponentStateRepository::class)]
class WorkerComponentState implements WorkerComponentStateInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private readonly string $jobId;

    #[ORM\Id]
    #[ORM\Column(length: 64, nullable: false, enumType: WorkerComponentName::class)]
    private readonly WorkerComponentName $componentName;

    #[ORM\Column(length: 64)]
    private string $state;

    #[ORM\Column]
    private bool $isEndState;

    #[ORM\Column]
    private bool $stateIsEnded;

    #[ORM\Column]
    private bool $stateIsSucceeded;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(string $jobId, WorkerComponentName $componentName)
    {
        $this->jobId = $jobId;
        $this->componentName = $componentName;
        $this->isEndState = false;
        $this->stateIsEnded = false;
        $this->stateIsSucceeded = false;
    }

    /**
     * @param non-empty-string $state
     */
    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
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

    public function toArray(): array
    {
        return [
            'state' => '' === $this->state ? null : $this->state,
            'is_end_state' => $this->getMetaState()->ended,
            'meta_state' => $this->getMetaState()->jsonSerialize(),
        ];
    }
}
