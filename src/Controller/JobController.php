<?php

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Response\ErrorResponse;
use App\Services\UlidFactory;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ServiceClient\Exception\InvalidResponseContentException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\UsersSecurityBundle\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const ROUTE_SUITE_ID_PATTERN = '{suiteId<[A-Z90-9]{26}>}';

    /**
     * @param non-empty-string $suiteId
     *
     * @throws ClientExceptionInterface
     * @throws InvalidResponseDataException
     * @throws InvalidResponseContentException
     * @throws NonSuccessResponseException
     */
    #[Route('/' . self::ROUTE_SUITE_ID_PATTERN, name: 'job_create', methods: ['POST'])]
    public function create(
        string $suiteId,
        User $user,
        JobRepository $repository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
    ): JsonResponse {
        try {
            $id = $ulidFactory->create();
        } catch (EmptyUlidException) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'Generated job id is an empty string.');
        }

        $job = new Job($id, $user->getUserIdentifier(), $suiteId);
        $repository->add($job);

        $resultsJob = $resultsClient->createJob($user->getSecurityToken(), $id);
        if (!$resultsJob instanceof ResultsJob) {
            return new ErrorResponse(
                ErrorResponseType::SERVER_ERROR,
                'Failed creating job in results service.'
            );
        }

        if ('' === $resultsJob->token) {
            return new ErrorResponse(
                ErrorResponseType::SERVER_ERROR,
                'Results service job invalid, token missing.'
            );
        }

        $job->setResultsToken($resultsJob->token);
        $repository->add($job);

        return new JsonResponse($job);
    }
}
