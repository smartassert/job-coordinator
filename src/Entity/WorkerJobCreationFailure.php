<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerJobCreationStage;
use App\Repository\WorkerJobCreationFailureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerJobCreationFailureRepository::class)]
class WorkerJobCreationFailure
{
    #[ORM\Id]
    #[ORM\Column(length: 32, unique: true, nullable: false)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $stage;

    #[ORM\Column(type: Types::TEXT)]
    private string $exceptionClass;

    #[ORM\Column]
    private int $exceptionCode;

    #[ORM\Column(type: Types::TEXT)]
    private string $exceptionMessage;

    /**
     * @param non-empty-string $id
     * @param class-string     $exceptionClass
     */
    public function __construct(
        string $id,
        WorkerJobCreationStage $stage,
        string $exceptionClass,
        int $exceptionCode,
        string $exceptionMessage
    ) {
        $this->id = $id;
        $this->stage = $stage->value;
        $this->exceptionClass = $exceptionClass;
        $this->exceptionCode = $exceptionCode;
        $this->exceptionMessage = $exceptionMessage;
    }

    /**
     * @return array{
     *   stage: string,
     *   exception: array{
     *     class: string,
     *     code: int,
     *     message: string
     *   }
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'stage' => $this->stage,
            'exception' => [
                'class' => $this->exceptionClass,
                'code' => $this->exceptionCode,
                'message' => $this->exceptionMessage,
            ],
        ];
    }
}
