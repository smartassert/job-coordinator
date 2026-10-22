<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\Machine\ActionFailureInterface;
use App\Repository\MachineActionFailureRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type SerializedMachineActionFailure array{
 *   action: ?non-empty-string,
 *   type: ?non-empty-string,
 *   context: null|array<mixed>
 * }
 * @phpstan-type MachineActionFailureContext array<string,null|int|string>
 */
#[ORM\Entity(repositoryClass: MachineActionFailureRepository::class)]
class MachineActionFailure implements ActionFailureInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $type;

    /**
     * @var MachineActionFailureContext
     */
    #[ORM\Column()]
    private array $context;

    /**
     * @param non-empty-string            $id
     * @param non-empty-string            $action
     * @param non-empty-string            $type
     * @param MachineActionFailureContext $context
     */
    public function __construct(
        string $id,
        string $action,
        string $type,
        array $context,
    ) {
        $this->id = $id;
        $this->action = $action;
        $this->type = $type;
        $this->context = $context;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array{
     *   action: ?non-empty-string,
     *   type: ?non-empty-string,
     *   context: MachineActionFailureContext
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => '' === $this->action ? null : $this->action,
            'type' => '' === $this->type ? null : $this->type,
            'context' => $this->context,
        ];
    }
}
