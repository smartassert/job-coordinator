<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\PreparationState;
use App\Model\ComponentPreparation;

class PreparationStateReducer
{
    /**
     * @param PreparationState[] $preparationStates
     */
    public function reduce(array $preparationStates): PreparationState
    {
        /**
         * @param null|PreparationState $previous
         * @param ComponentPreparation  $item
         *
         * @return PreparationState
         */
        $reducer = function (?PreparationState $previous, PreparationState $item): PreparationState {
            if (null === $previous) {
                return $item;
            }

            if (PreparationState::FAILED === $previous || PreparationState::FAILED === $item) {
                return PreparationState::FAILED;
            }

            if (PreparationState::SUCCEEDED === $previous && PreparationState::SUCCEEDED === $item) {
                return PreparationState::SUCCEEDED;
            }

            if (PreparationState::PENDING === $previous && PreparationState::PENDING === $item) {
                return PreparationState::PENDING;
            }

            return PreparationState::PREPARING;
        };

        return array_reduce($preparationStates, $reducer) ?? PreparationState::PENDING;
    }
}
