<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Enum\RequestState;
use App\Model\RemoteRequestInterface;
use App\Model\TypedRemoteRequestInterface;
use App\Repository\RemoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 */
#[ORM\Entity(repositoryClass: RemoteRequestRepository::class)]
#[ORM\Index(columns: ['job_id', 'entity', 'action'], name: 'job_type_idx')]
class RemoteRequest implements RemoteRequestInterface, TypedRemoteRequestInterface
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    public readonly string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    private readonly string $jobId;

    #[ORM\Column(length: 64, nullable: false, enumType: RemoteRequestEntity::class)]
    private readonly RemoteRequestEntity $entity;

    #[ORM\Column(length: 64, nullable: false, enumType: RemoteRequestAction::class)]
    private readonly RemoteRequestAction $action;

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
     * @param int<0, max>      $index
     */
    public function __construct(
        string $jobId,
        RemoteRequestEntity $entity,
        RemoteRequestAction $action,
        int $index = 0
    ) {
        $this->id = self::generateId($jobId, $entity, $action, $index);
        $this->jobId = $jobId;
        $this->entity = $entity;
        $this->action = $action;
        $this->state = RequestState::REQUESTING;
        $this->index = $index;
    }

    /**
     * @return non-empty-string
     */
    public function getType(): string
    {
        return $this->entity->value . '/' . $this->action->value;
    }

    public function getState(): RequestState
    {
        return $this->state;
    }

    public function setState(RequestState $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @param non-empty-string $jobId
     * @param int<0, max>      $index
     *
     * @return non-empty-string
     */
    public static function generateId(
        string $jobId,
        RemoteRequestEntity $entity,
        RemoteRequestAction $action,
        int $index
    ): string {
        return $jobId . $entity->value . $action->value . $index;
    }

    public function setFailure(RemoteRequestFailure $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function getFailure(): ?RemoteRequestFailure
    {
        return $this->failure;
    }

    /**
     * @return SerializedRemoteRequest
     */
    public function toArray(): array
    {
        $data = [
            'state' => $this->state->value,
        ];

        if ($this->failure instanceof RemoteRequestFailure) {
            $data['failure'] = $this->failure;
        }

        return $data;
    }
}
