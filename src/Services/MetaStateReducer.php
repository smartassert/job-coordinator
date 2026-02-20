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
            return new MetaState(true, false);
        }

        $ended = true;
        $succeeded = true;

        foreach ($metaStates as $metaState) {
            if (null === $metaState) {
                $metaState = new MetaState(false, false);
            }

            $ended = $ended && $metaState->ended;
            $succeeded = $succeeded && $metaState->succeeded;
        }

        return new MetaState($ended, $succeeded);
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
