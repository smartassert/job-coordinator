<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\PreparationState;
use App\Model\ComponentPreparation;

class PreparationStateReducer
{
    /**
     * @param ComponentPreparation[] $componentPreparationStates
     */
    public function reduce(array $componentPreparationStates): PreparationState
    {
        /**
         * @param null|PreparationState $previous
         * @param ComponentPreparation  $item
         *
         * @return PreparationState
         */
        $reducer = function (?PreparationState $previous, ComponentPreparation $item): PreparationState {
            $current = $item->state;

            if (null === $previous) {
                return $current;
            }

            if (PreparationState::FAILED === $previous || PreparationState::FAILED === $current) {
                return PreparationState::FAILED;
            }

            if (PreparationState::SUCCEEDED === $previous && PreparationState::SUCCEEDED === $current) {
                return PreparationState::SUCCEEDED;
            }

            if (PreparationState::PENDING === $previous && PreparationState::PENDING === $current) {
                return PreparationState::PENDING;
            }

            return PreparationState::PREPARING;
        };

        return array_reduce($componentPreparationStates, $reducer) ?? PreparationState::PENDING;
    }
}
