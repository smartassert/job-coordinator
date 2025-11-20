<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;

readonly class RemoteRequestType implements \Stringable
{
    public function __construct(
        public JobComponent $jobComponent,
        public RemoteRequestAction $action,
    ) {}

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->jobComponent->value . '/' . $this->action->value;
    }

    public static function createForMachineCreation(): self
    {
        return new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE);
    }
}
