<?php

declare(strict_types=1);

namespace App\Controller;

use App\Event\JobCreatedEvent;
use App\Model\JobInterface;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Services\JobStatusFactory;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\UsersSecurityBundle\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

readonly class JobController
{
    public function __construct(
        private JobRepository $jobRepository,
        private JobStatusFactory $jobStatusFactory,
        private JobStore $jobStore,
    ) {
    }

    #[Route('/{suiteId<[A-Z90-9]{26}>}', name: 'job_create', methods: ['POST'])]
    public function create(
        CreateJobRequest $request,
        User $user,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        $job = $this->jobStore->create(
            $user->getUserIdentifier(),
            $request->suiteId,
            $request->maximumDurationInSeconds
        );

        $eventDispatcher->dispatch(
            new JobCreatedEvent($user->getSecurityToken(), $job->getId(), $job->getSuiteId(), $request->parameters)
        );

        return new JsonResponse($this->jobStatusFactory->create($job));
    }

    #[Route('/{jobId<[A-Z90-9]{26}>}', name: 'job_get', methods: ['GET'])]
    public function get(JobInterface $job): Response
    {
        return new JsonResponse($this->jobStatusFactory->create($job));
    }

    #[Route('/{suiteId<[A-Z90-9]{26}>}/list', name: 'job_list', methods: ['GET'])]
    public function list(User $user, string $suiteId): Response
    {
        $jobEntities = $this->jobRepository->findBy(
            [
                'userId' => $user->getUserIdentifier(),
                'suiteId' => $suiteId,
            ],
            [
                'id' => 'DESC',
            ]
        );

        return new JsonResponse($this->jobStore->hydrateFromJobEntities($jobEntities));
    }
}
