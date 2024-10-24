<?php

declare(strict_types=1);

namespace App\Event;

interface AuthenticatingEventInterface
{
    /**
     * @return non-empty-string
     */
    public function getAuthenticationToken(): string;
}
