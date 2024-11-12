<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MachineActionFailureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineActionFailureRepository::class)]
class MachineActionFailure implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $type;

    /**
     * @var null|array<mixed>
     */
    #[ORM\Column(nullable: true)]
    private ?array $context;

    /**
     * @param non-empty-string  $id
     * @param non-empty-string  $action
     * @param non-empty-string  $type
     * @param null|array<mixed> $context
     */
    public function __construct(
        string $id,
        string $action,
        string $type,
        ?array $context = null
    ) {
        $this->id = $id;
        $this->action = $action;
        $this->type = $type;
        $this->context = $context;
    }

    /**
     * @return array{
     *   action: ?non-empty-string,
     *   type: ?non-empty-string,
     *   context: array<mixed>|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'action' => '' === $this->action ? null : $this->action,
            'type' => '' === $this->type ? null : $this->type,
            'context' => $this->context,
        ];
    }
}
