<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Message\GetSerializedSuiteStateMessage;
use App\Message\MachineStateChangeCheckMessage;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\ErrorResponseFactory;
use App\Services\UlidFactory;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ServiceClient\Exception\HttpResponseExceptionInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\UsersSecurityBundle\Security\User;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Exception\CreateMachineException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    /**
     * @throws ClientExceptionInterface
     */
    #[Route('/' . JobRoutes::ROUTE_SUITE_ID_PATTERN, name: 'job_create', methods: ['POST'])]
    public function create(
        CreateJobRequest $request,
        User $user,
        JobRepository $repository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
        ErrorResponseFactory $errorResponseFactory,
        WorkerManagerClient $workerManagerClient,
        SerializedSuiteClient $serializedSuiteClient,
        MessageBusInterface $messageBus,
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

        try {
            $serializedSuite = $serializedSuiteClient->create(
                $user->getSecurityToken(),
                $request->suiteId,
                $request->parameters,
            );
        } catch (HttpResponseExceptionInterface $exception) {
            return $errorResponseFactory->createFromHttpResponseException(
                $exception,
                'Failed requesting suite serialization in sources service.'
            );
        }

        $job = new Job(
            $id,
            $user->getUserIdentifier(),
            $request->suiteId,
            $serializedSuite->getId(),
            $request->maximumDurationInSeconds,
        );
        $job = $job->setResultsToken($resultsJob->token);

        $repository->add($job);

        $messageBus->dispatch(new MachineStateChangeCheckMessage($user->getSecurityToken(), $machine));
        $messageBus->dispatch(new GetSerializedSuiteStateMessage($user->getSecurityToken(), $serializedSuite->getId()));

        return new JsonResponse($job);
    }

    #[Route('/' . JobRoutes::ROUTE_JOB_ID_PATTERN, name: 'job_get', methods: ['GET'])]
    public function get(string $jobId, User $user, JobRepository $repository): Response
    {
        $job = $repository->find($jobId);
        if (null === $job) {
            return new Response(null, 404);
        }

        if ($job->userId !== $user->getUserIdentifier()) {
            return new Response(null, 401);
        }

        return new JsonResponse($job);
    }
}
