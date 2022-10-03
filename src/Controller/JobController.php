<?php

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Response\ErrorResponse;
use App\Services\UlidFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class JobController
{
    #[Route('/', name: 'job_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserInterface $user,
        JobRepository $repository,
        UlidFactory $ulidFactory,
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

        return new JsonResponse($job);
    }
}
