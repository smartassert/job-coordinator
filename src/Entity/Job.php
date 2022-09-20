<?php

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private readonly string $userId;

    #[ORM\Column(length: 32)]
    private readonly string $suiteId;

    #[ORM\Column(length: 32)]
    private readonly string $label;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $label
     */
    public function __construct(
        string $userId,
        string $suiteId,
        string $label,
    ) {
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getSuiteId(): string
    {
        return $this->suiteId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
