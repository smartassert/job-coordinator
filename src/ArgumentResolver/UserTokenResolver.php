<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use SmartAssert\UsersSecurityBundle\Security\SymfonyRequestTokenExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class UserTokenResolver implements ArgumentValueResolverInterface
{
    public function __construct(
        private readonly SymfonyRequestTokenExtractor $tokenExtractor,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return 'string' === $argument->getType() && 'userToken' === $argument->getName();
    }

    /**
     * @return \Traversable<?non-empty-string>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Traversable
    {
        $token = (string) $this->tokenExtractor->extract($request);

        yield '' === $token ? null : $token;
    }
}
