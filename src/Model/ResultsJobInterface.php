<?php

declare(strict_types=1);

namespace App\Model;

interface ResultsJobInterface
{
    /**
     * @return ?non-empty-string
     */
    public function getState(): ?string;

    /**
     * @return ?non-empty-string
     */
    public function getEndState(): ?string;
}
