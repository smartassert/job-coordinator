<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RemoteRequestFailureType;
use App\Repository\RemoteRequestFailureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemoteRequestFailureRepository::class)]
class RemoteRequestFailure implements \JsonSerializable
{
    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, enumType: RemoteRequestFailureType::class)]
    public readonly RemoteRequestFailureType $type;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $code;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $message = null;

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
     * @return array{type: non-empty-string, code: int, message: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
