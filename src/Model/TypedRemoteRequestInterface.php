<?php

declare(strict_types=1);

namespace App\Model;

interface TypedRemoteRequestInterface
{
    /**
     * @return ?non-empty-string
     */
    public function getType(): ?string;
}
