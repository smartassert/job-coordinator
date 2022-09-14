<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    #[Route('/', name: 'job_create', methods: ['POST'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([]);
    }
}
