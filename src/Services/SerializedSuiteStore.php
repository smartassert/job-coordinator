<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\SerializedSuite as SerializedSuiteEntity;
use App\Model\SerializedSuite;
use App\Repository\SerializedSuiteRepository;

class SerializedSuiteStore
{
    public function __construct(
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

    public function retrieve(string $jobId): ?SerializedSuite
    {
        $entity = $this->serializedSuiteRepository->find($jobId);
        if (null === $entity) {
            return null;
        }

        return $this->hydrateFromEntity($entity);
    }

    private function hydrateFromEntity(SerializedSuiteEntity $entity): ?SerializedSuite
    {
        $id = $entity->getId();
        if (null === $id) {
            return null;
        }

        if ('' === $entity->getState()) {
            return null;
        }

        return new SerializedSuite($id, $entity->getState(), $entity->isPrepared(), $entity->hasEndState());
    }
}
