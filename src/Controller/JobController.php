<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Enum\ErrorResponseType;
use App\Event\JobCreatedEvent;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\JobSerializer;
use App\Services\UlidFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\UsersSecurityBundle\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    #[Route('/' . JobRoutes::ROUTE_SUITE_ID_PATTERN, name: 'job_create', methods: ['POST'])]
    public function create(
        CreateJobRequest $request,
        User $user,
        JobRepository $repository,
        JobSerializer $jobSerializer,
        UlidFactory $ulidFactory,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        try {
            $id = $ulidFactory->create();
        } catch (EmptyUlidException) {
            return new ErrorResponse(ErrorResponseType::SERVER_ERROR, 'Generated job id is an empty string.');
        }

        $job = new Job($user->getUserIdentifier(), $request->suiteId, $request->maximumDurationInSeconds);
        $repository->add($job);

        $eventDispatcher->dispatch(new JobCreatedEvent($user->getSecurityToken(), $job->id, $request->parameters));

        return new JsonResponse($jobSerializer->serialize($job));
    }

    #[Route('/' . JobRoutes::ROUTE_JOB_ID_PATTERN, name: 'job_get', methods: ['GET'])]
    public function get(string $jobId, User $user, JobRepository $repository, JobSerializer $jobSerializer): Response
    {
        $job = $repository->find($jobId);
        if (null === $job) {
            return new Response(null, 404);
        }

        if ($job->userId !== $user->getUserIdentifier()) {
            return new Response(null, 401);
        }

        return new JsonResponse($jobSerializer->serialize($job));
    }
}
