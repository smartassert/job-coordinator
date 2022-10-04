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
use SmartAssert\UsersSecurityBundle\Security\SymfonyRequestTokenExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class JobController
{
    /**
     * @throws ClientExceptionInterface
     * @throws InvalidResponseDataException
     * @throws InvalidResponseContentException
     * @throws NonSuccessResponseException
     */
    #[Route('/', name: 'job_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserInterface $user,
        JobRepository $repository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
        SymfonyRequestTokenExtractor $tokenExtractor,
    ): JsonResponse {
        $userId = trim($user->getUserIdentifier());
        if ('' === $userId) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'User identifier is empty.');
        }

        $suiteId = $request->request->get('suite_id');
        $suiteId = is_string($suiteId) ? trim($suiteId) : '';
        if ('' === $suiteId) {
            return new ErrorResponse(
                ErrorResponseType::INVALID_REQUEST,
                'Required field "suite_id" invalid, missing from request or is an empty string.'
            );
        }

        try {
            $label = $ulidFactory->create();
        } catch (EmptyUlidException) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'Generated job label is an empty string.');
        }

        $job = new Job($userId, $suiteId, $label);
        $repository->add($job);

        $userToken = (string) $tokenExtractor->extract($request);
        if ('' === $userToken) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'Request user token is empty.');
        }

        $resultsJob = $resultsClient->createJob($userToken, $label);
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
