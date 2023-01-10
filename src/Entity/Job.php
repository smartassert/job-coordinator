<?php

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
class Job implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    public readonly string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $userId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    public readonly string $suiteId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32, nullable: true)]
    public readonly string $resultsToken;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $id
     * @param non-empty-string $resultsToken
     */
    public function __construct(string $id, string $userId, string $suiteId, string $resultsToken)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->resultsToken = $resultsToken;
    }

    /**
     * @return array{suite_id: non-empty-string, id: non-empty-string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suiteId,
        ];
    }
}
