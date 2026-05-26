<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\MetaState;

readonly class MetaStateReducer
{
    /**
     * @param array<null|MetaState> $metaStates
     */
    public function reduce(array $metaStates): MetaState
    {
        if ($this->hasAnyComponentFailed($metaStates)) {
            return new MetaState(true, false, false);
        }

        $ended = true;
        $succeeded = true;
        $pending = true;

        foreach ($metaStates as $metaState) {
            if (null === $metaState) {
                $metaState = new MetaState(false, false, true);
            }

            $ended = $ended && $metaState->ended;
            $succeeded = $succeeded && $metaState->succeeded;
            $pending = $pending && $metaState->pending;
        }

        return new MetaState($ended, $succeeded, $pending);
    }

    /**
     * @param array<null|MetaState> $metaStates
     */
    private function hasAnyComponentFailed(array $metaStates): bool
    {
        foreach ($metaStates as $metaState) {
            if (null === $metaState) {
                continue;
            }

            if (true === $metaState->ended && false === $metaState->succeeded) {
                return true;
            }
        }

        return false;
    }
}
