<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Message\GetSerializedSuiteStateMessage;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use App\MessageDispatcher\SerializedSuiteStateChangeCheckMessageDispatcher;
use App\Model\Machine;
use App\Model\SerializedSuite;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\ErrorResponseFactory;
use App\Services\UlidFactory;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ServiceClient\Exception\HttpResponseExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\UsersSecurityBundle\Security\User;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Exception\CreateMachineException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
        MachineStateChangeCheckMessageDispatcher $machineStateChangeCheckMessageDispatcher,
        SerializedSuiteStateChangeCheckMessageDispatcher $serializedSuiteStateChangeCheckMessageDispatcher,
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
            $resultsJob->token,
            $serializedSuite->getId(),
            $request->maximumDurationInSeconds,
        );
        $repository->add($job);

        $machineStateChangeCheckMessageDispatcher->dispatch(
            new MachineStateChangeCheckMessage($user->getSecurityToken(), $machine)
        );

        $serializedSuiteStateChangeCheckMessageDispatcher->dispatch(
            new GetSerializedSuiteStateMessage($user->getSecurityToken(), $serializedSuite->getId())
        );

        return new JsonResponse([
            'job' => $job,
            'machine' => new Machine($machine),
        ]);
    }

    /**
     * @throws NonSuccessResponseException
     * @throws InvalidResponseDataException
     * @throws ClientExceptionInterface
     * @throws InvalidResponseTypeException
     * @throws HttpResponseExceptionInterface
     * @throws InvalidModelDataException
     */
    #[Route('/' . JobRoutes::ROUTE_JOB_ID_PATTERN, name: 'job_get', methods: ['GET'])]
    public function get(
        string $jobId,
        User $user,
        JobRepository $repository,
        WorkerManagerClient $workerManagerClient,
        SerializedSuiteClient $serializedSuiteClient,
    ): Response {
        $job = $repository->find($jobId);
        if (null === $job) {
            return new Response(null, 404);
        }

        if ($job->userId !== $user->getUserIdentifier()) {
            return new Response(null, 401);
        }

        $machine = $workerManagerClient->getMachine($user->getSecurityToken(), $job->id);
        $serializedSuite = $serializedSuiteClient->get($user->getSecurityToken(), $job->serializedSuiteId);

        return new JsonResponse([
            'job' => $job,
            'machine' => new Machine($machine),
            'serialized_suite' => new SerializedSuite($serializedSuite),
        ]);
    }
}
