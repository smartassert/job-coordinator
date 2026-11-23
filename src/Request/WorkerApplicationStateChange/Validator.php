<?php

declare(strict_types=1);

namespace App\Request\WorkerApplicationStateChange;

class Validator
{
    /**
     * @throws InvalidRequestException
     */
    public function validate(Request $request): ValidatedRequest
    {
        if (null === $request->jobId) {
            throw new InvalidRequestException(Request::KEY_JOB, 'a string');
        }

        if ([] === $request->stateData) {
            throw new InvalidRequestException(Request::KEY_STATE_DATA, 'a non-empty array');
        }

        return new ValidatedRequest($request->jobId, $request->stateData);
    }
}
