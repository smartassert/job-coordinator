<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-type SerializedJob array{
 *   id: ?non-empty-string,
 *   suite_id: ?non-empty-string,
 *   maximum_duration_in_seconds: positive-int,
 *   created_at: positive-int
 *  }
 */
#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
readonly class Job implements \JsonSerializable
{
    #[ORM\Column(length: 32)]
    public string $userId;

    #[ORM\Column(length: 32)]
    public string $suiteId;
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $id;

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

    /**
     * @return positive-int
     */
    public function getMaximumDurationInSeconds(): int
    {
        return max($this->maximumDurationInSeconds, 1);
    }

    /**
     * @return SerializedJob
     */
    public function toArray(): array
    {
        return [
            'id' => '' === $this->id ? null : $this->id,
            'suite_id' => '' === $this->suiteId ? null : $this->suiteId,
            'maximum_duration_in_seconds' => $this->getMaximumDurationInSeconds(),
            'created_at' => max($this->getCreatedAt(), 1),
        ];
    }

    /**
     * @return SerializedJob
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function getCreatedAt(): int
    {
        $idAsUlid = new Ulid($this->id);

        return (int) $idAsUlid->getDateTime()->format('U');
    }
}
