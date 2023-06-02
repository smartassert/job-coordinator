<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Entity\RemoteRequest;
use App\Exception\MachineRetrievalException;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class MachineRetrievalExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureFactory $remoteRequestFailureFactory,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof MachineRetrievalException) {
            return;
        }

        $remoteRequest = $this->remoteRequestRepository->findOneBy([
            'jobId' => $throwable->machine->id,
        ]);

        if ($remoteRequest instanceof RemoteRequest) {
            $remoteRequestFailure = $this->remoteRequestFailureFactory->create($throwable->previousException);

            if (null !== $remoteRequestFailure) {
                $remoteRequest->setFailure($remoteRequestFailure);
                $this->remoteRequestRepository->save($remoteRequest);
            }
        }
    }
}
