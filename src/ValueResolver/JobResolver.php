<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Model\JobInterface;
use App\Services\JobStore;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class JobResolver implements ValueResolverInterface
{
    public function __construct(
        private JobStore $jobStore,
        private Security $security,
    ) {
    }

    /**
     * @return JobInterface[]
     *
     * @throws AccessDeniedException
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (JobInterface::class !== $argument->getType()) {
            return [];
        }

        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException();
        }

        $jobId = $request->attributes->getString('jobId');
        $job = $this->jobStore->retrieve($jobId);
        if (null === $job) {
            throw new AccessDeniedException();
        }

        if ($job->getUserId() !== $user->getUserIdentifier()) {
            throw new AccessDeniedException();
        }

        return [$job];
    }
}
