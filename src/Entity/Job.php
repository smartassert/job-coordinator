<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
readonly class Job
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(UlidGenerator::class)]
    #[ORM\Column(type: 'ulid', unique: true)]
    public string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public string $userId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public string $suiteId;

    /**
     * @var positive-int
     */
    #[ORM\Column]
    public int $maximumDurationInSeconds;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param positive-int     $maximumDurationInSeconds
     */
    public function __construct(
        string $userId,
        string $suiteId,
        int $maximumDurationInSeconds,
        \DateTimeImmutable $createdAt
    ) {
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->createdAt = $createdAt;
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   maximum_duration_in_seconds: positive-int,
     *   created_at: positive-int
     *  }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
            'created_at' => max((int) $this->createdAt->format('U'), 1),
        ];
    }
}
