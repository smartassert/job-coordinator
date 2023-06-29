<?php

declare(strict_types=1);

namespace App\Model;

class PendingResultsJob implements ResultsJobInterface
{
    public function getState(): ?string
    {
        return null;
    }

    public function getEndState(): ?string
    {
        return null;
    }
}
