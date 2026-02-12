<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RequestState;
use App\Model\RemoteRequestInterface;
use App\Model\RemoteRequestType;
use App\Model\TypedRemoteRequestInterface;
use App\Repository\RemoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 */
#[ORM\Entity(repositoryClass: RemoteRequestRepository::class)]
#[ORM\Index(columns: ['job_id', 'type'], name: 'job_type_idx')]
class RemoteRequest implements RemoteRequestInterface, TypedRemoteRequestInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    public readonly string $id;

    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    private readonly string $jobId;

    #[ORM\Column(nullable: false, length: 64)]
    private readonly string $type;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, enumType: RequestState::class)]
    private RequestState $state;

    #[ORM\ManyToOne(cascade: ['persist'])]
    private ?RemoteRequestFailure $failure = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private readonly int $index;

    /**
     * @param non-empty-string $jobId
     * @param int<0, max>      $index
     */
    public function __construct(
        string $jobId,
        RemoteRequestType $type,
        int $index = 0
    ) {
        $this->id = self::generateId($jobId, $type, $index);
        $this->jobId = $jobId;
        $this->type = (string) $type;
        $this->state = RequestState::REQUESTING;
        $this->index = $index;
    }

    /**
     * @return ?non-empty-string
     */
    public function getType(): ?string
    {
        return '' === $this->type ? null : $this->type;
    }

    public function getState(): RequestState
    {
        return $this->state;
    }

    public function setState(RequestState $state): self
    {
        if ($this->allowsStateChange($state)) {
            $this->state = $state;
        }

        return $this;
    }

    public function hasEndState(): bool
    {
        return in_array($this->state, RequestState::endStates());
    }

    /**
     * @param non-empty-string $jobId
     * @param int<0, max>      $index
     *
     * @return non-empty-string
     */
    public static function generateId(string $jobId, RemoteRequestType $type, int $index): string
    {
        return $jobId . $type . $index;
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

    private function allowsStateChange(RequestState $state): bool
    {
        if (in_array($this->state, RequestState::endStates())) {
            return false;
        }

        if (RequestState::HALTED === $this->state) {
            return RequestState::REQUESTING === $state;
        }

        if (RequestState::PENDING === $this->state) {
            return RequestState::REQUESTING === $state || RequestState::HALTED === $state;
        }

        if (RequestState::REQUESTING === $this->state) {
            return
                RequestState::REQUESTING === $state
                || RequestState::HALTED === $state
                || RequestState::FAILED === $state
                || RequestState::SUCCEEDED === $state
                || RequestState::ABORTED === $state;
        }

        return false;
    }
}
