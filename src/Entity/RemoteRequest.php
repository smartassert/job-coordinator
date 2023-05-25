<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\RemoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemoteRequestRepository::class)]
#[ORM\Index(columns: ['job_id', 'type'], name: 'job_type_idx')]
class RemoteRequest
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private readonly string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    private readonly string $jobId;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, enumType: RemoteRequestType::class)]
    private readonly RemoteRequestType $type;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, enumType: RequestState::class)]
    private RequestState $state;

    #[ORM\ManyToOne(cascade: ['persist'])]
    private ?RemoteRequestFailure $failure = null;

    /**
     * @var int<0, max>
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private readonly int $index;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(string $jobId, RemoteRequestType $type)
    {
        $this->id = self::generateId($jobId, $type);
        $this->jobId = $jobId;
        $this->type = $type;
        $this->state = RequestState::REQUESTING;
        $this->index = 0;
    }

    public function setState(RequestState $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @param non-empty-string $jobId
     *
     * @return non-empty-string
     */
    public static function generateId(string $jobId, RemoteRequestType $type): string
    {
        return $jobId . $type->value;
    }

    public function setFailure(?RemoteRequestFailure $failure): self
    {
        $this->failure = $failure;

        return $this;
    }
}
