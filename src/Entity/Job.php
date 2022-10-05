<?php

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Index(name: 'user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'user_suite_idx', columns: ['user_id', 'suite_id'])]
class Job implements \JsonSerializable
{
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
    #[ORM\Column(length: 32)]
    private readonly string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 32, nullable: true)]
    private string $resultsToken;

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param non-empty-string $label
     */
    public function __construct(string $userId, string $suiteId, string $label)
    {
        $this->id = $label;
        $this->userId = $userId;
        $this->suiteId = $suiteId;
        $this->label = $label;
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
    public function getLabel(): string
    {
        return $this->label;
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
     * @return array{suite_id: non-empty-string, label: non-empty-string}
     */
    public function jsonSerialize(): array
    {
        return [
            'suite_id' => $this->suiteId,
            'label' => $this->label,
        ];
    }
}
