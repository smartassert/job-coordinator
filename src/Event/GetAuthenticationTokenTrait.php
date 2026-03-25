<?php

declare(strict_types=1);

namespace App\Event;

trait GetAuthenticationTokenTrait
{
    /**
     * @return non-empty-string
     */
    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }
}
