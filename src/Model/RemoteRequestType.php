<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

readonly class RemoteRequestType
{
    public function __construct(
        public RemoteRequestEntity $entity,
        public RemoteRequestAction $action,
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function serialize(): string
    {
        return $this->entity->value . '/' . $this->action->value;
    }
}
