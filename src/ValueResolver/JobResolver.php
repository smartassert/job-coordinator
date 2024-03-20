<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Entity\Job;
use App\Repository\JobRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class JobResolver implements ValueResolverInterface
{
    public function __construct(
        private JobRepository $jobRepository,
        private Security $security,
    ) {
    }

    /**
     * @return Job[]
     *
     * @throws AccessDeniedException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (Job::class !== $argument->getType()) {
            return [];
        }

        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException();
        }

        $jobId = $request->attributes->getString('jobId');
        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            throw new AccessDeniedException();
        }

        if ($job->userId !== $user->getUserIdentifier()) {
            throw new AccessDeniedException();
        }

        return [$job];
    }
}
