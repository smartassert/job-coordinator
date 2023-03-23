<?php

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\ErrorResponseFactory;
use App\Services\UlidFactory;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ServiceClient\Exception\HttpResponseExceptionInterface;
use SmartAssert\UsersSecurityBundle\Security\User;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Exception\CreateMachineException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const ROUTE_SUITE_ID_PATTERN = '{suiteId<[A-Z90-9]{26}>}';

    /**
     * @throws ClientExceptionInterface
     */
    #[Route('/' . self::ROUTE_SUITE_ID_PATTERN, name: 'job_create', methods: ['POST'])]
    public function create(
        CreateJobRequest $request,
        User $user,
        JobRepository $repository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
        ErrorResponseFactory $errorResponseFactory,
        WorkerManagerClient $workerManagerClient,
    ): JsonResponse {
        try {
            $id = $ulidFactory->create();
        } catch (EmptyUlidException) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'Generated job id is an empty string.');
        }

        try {
            $resultsJob = $resultsClient->createJob($user->getSecurityToken(), $id);
        } catch (HttpResponseExceptionInterface $exception) {
            return $errorResponseFactory->createFromHttpResponseException(
                $exception,
                'Failed creating job in results service.'
            );
        }

        if ('' === $resultsJob->token) {
            return new ErrorResponse(
                ErrorResponseType::SERVER_ERROR,
                'Results service job invalid, token missing.'
            );
        }

        try {
            $machine = $workerManagerClient->createMachine($user->getSecurityToken(), $id);
        } catch (HttpResponseExceptionInterface $httpResponseException) {
            return $errorResponseFactory->createFromHttpResponseException(
                $httpResponseException,
                'Failed requesting worker machine creation.'
            );
        } catch (CreateMachineException $createMachineException) {
            return $errorResponseFactory->createFromThrowable(
                ErrorResponseType::SERVER_ERROR,
                'Failed requesting worker machine creation.',
                $createMachineException
            );
        }

        $job = new Job($id, $user->getUserIdentifier(), $request->suiteId, $resultsJob->token);
        $repository->add($job);

        return new JsonResponse([
            'job' => $job->jsonSerialize(),
            'machine' => [
                'id' => $machine->id,
                'state' => $machine->state,
                'ip_addresses' => $machine->ipAddresses,
            ],
        ]);
    }
}
