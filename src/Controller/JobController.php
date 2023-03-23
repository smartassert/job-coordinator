<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use App\Repository\JobRepository;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const ROUTE_SUITE_ID_PATTERN = '{suiteId<[A-Z90-9]{26}>}';

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
            MachineStateChangeCheckMessage::createFromMachine($user->getSecurityToken(), $machine)
        );

        return new JsonResponse([
            'job' => $job->jsonSerialize(),
            'machine' => [
                'id' => $machine->id,
                'state' => $machine->state,
                'ip_addresses' => $machine->ipAddresses,
            ],
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
