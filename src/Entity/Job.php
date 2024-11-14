<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
readonly class Job
{
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $id;

    #[ORM\Column(length: 32)]
    private string $userId;

    #[ORM\Column(length: 32)]
    private string $suiteId;

    #[ORM\Column]
    private int $maximumDurationInSeconds;

    /**
     * @param non-empty-string $id
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param positive-int     $maximumDurationInSeconds
     */
    public function __construct(string $id, string $userId, string $suiteId, int $maximumDurationInSeconds)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
    }

    public function getId(): string
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

    /**
     * @return positive-int
     */
    public function getMaximumDurationInSeconds(): int
    {
        return max($this->maximumDurationInSeconds, 1);
    }
}
