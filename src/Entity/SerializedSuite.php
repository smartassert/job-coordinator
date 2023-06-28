<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SerializedSuiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SerializedSuiteRepository::class)]
class SerializedSuite
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

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function __construct(
        string $jobId,
        string $serializedSuiteId,
        string $state,
    ) {
        $this->jobId = $jobId;
        $this->serializedSuiteId = $serializedSuiteId;
        $this->state = $state;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->serializedSuiteId;
    }

    /**
     * @return non-empty-string
     */
    public function getState(): ?string
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
}
