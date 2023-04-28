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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const ROUTE_SUITE_ID_PATTERN = '{suiteId<[A-Z90-9]{26}>}';
    public const ROUTE_JOB_ID_PATTERN = '{jobId<[A-Z90-9]{26}>}';

    /**
     * @param non-empty-string $suiteId
     *
     * @throws ClientExceptionInterface
     */
    #[Route('/' . self::ROUTE_SUITE_ID_PATTERN, name: 'job_create', methods: ['POST'])]
    public function create(
        Request $request,
        string $suiteId,
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
                $suiteId,
                $this->createSuiteSerializationParameters($request)
            );
        } catch (HttpResponseExceptionInterface $exception) {
            return $errorResponseFactory->createFromHttpResponseException(
                $exception,
                'Failed requesting suite serialization in sources service.'
            );
        }

        $job = new Job($id, $user->getUserIdentifier(), $suiteId, $resultsJob->token, $serializedSuite->getId());
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
    #[Route('/' . self::ROUTE_JOB_ID_PATTERN, name: 'job_get', methods: ['GET'])]
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

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    private function createSuiteSerializationParameters(Request $request): array
    {
        if ('application/json' !== $request->getContentTypeFormat()) {
            return [];
        }

        $requestPayload = $request->request;
        if (!$requestPayload->has('parameters')) {
            return [];
        }

        $requestParameters = $requestPayload->get('parameters');
        if (!is_string($requestParameters)) {
            return [];
        }

        $decodedRequestParameters = json_decode($requestParameters, true);
        if (!is_array($decodedRequestParameters)) {
            return [];
        }

        $parameters = [];
        foreach ($decodedRequestParameters as $key => $value) {
            if (is_string($key) && '' !== $key && is_string($value) && '' !== $value) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }
}
