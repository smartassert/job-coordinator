<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RemoteRequestFailureType;
use App\Repository\RemoteRequestFailureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type SerializedRemoteRequestFailure array{
 *   type: value-of<RemoteRequestFailureType>,
 *   code: int,
 *   message: ?string
 * }
 */
#[ORM\Entity(repositoryClass: RemoteRequestFailureRepository::class)]
class RemoteRequestFailure
{
    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, enumType: RemoteRequestFailureType::class)]
    private readonly RemoteRequestFailureType $type;

    #[ORM\Column(type: Types::SMALLINT)]
    private readonly int $code;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private readonly ?string $message;

    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private readonly string $id;

    /**
     * @param non-empty-string $id
     */
    public function __construct(string $id, RemoteRequestFailureType $type, int $code, ?string $message)
    {
        $this->id = $id;
        $this->type = $type;
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * @return SerializedRemoteRequestFailure
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
