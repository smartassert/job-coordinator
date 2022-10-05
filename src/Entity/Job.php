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
    private string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    private readonly string $userId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32)]
    private readonly string $suiteId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32, nullable: true)]
    private string $resultsToken;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $id
     */
    public function __construct(string $id, string $userId, string $suiteId)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
    }

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
     * @return non-empty-string
     */
    public function getResultsToken(): string
    {
        return $this->resultsToken;
    }

    /**
     * @param non-empty-string $resultsToken
     */
    public function setResultsToken(string $resultsToken): void
    {
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
