<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\JobInterface;
use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-import-type SerializedJob from JobInterface
 */
#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
readonly class Job implements JobInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    public string $id;

    #[ORM\Column(length: 32)]
    public string $userId;

    #[ORM\Column(length: 32)]
    public string $suiteId;

    #[ORM\Column]
    public int $maximumDurationInSeconds;

    #[ORM\Column(type: 'text')]
    public string $token;

    /**
     * @param non-empty-string $id
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param positive-int     $maximumDurationInSeconds
     * @param non-empty-string $token
     */
    public function __construct(
        string $id,
        string $userId,
        string $suiteId,
        int $maximumDurationInSeconds,
        string $token,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->token = $token;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @return non-empty-string
     */
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

    /**
     * @return non-empty-string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return SerializedJob
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
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
