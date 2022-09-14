<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class JobController
{
    #[Route('/', name: 'job_create', methods: ['POST'])]
    public function index(UserInterface $user): JsonResponse
    {
        return new JsonResponse([]);
    }
}
