<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Model\RemoteRequestFailure as RemoteRequestFailureModel;
use App\Repository\RemoteRequestFailureRepository;

class RemoteRequestFailureFactory
{
    /**
     * @param iterable<ExceptionHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
        private readonly RemoteRequestFailureRepository $remoteRequestFailureRepository,
    ) {
    }

    public function create(\Throwable $throwable): ?RemoteRequestFailureEntity
    {
        foreach ($this->handlers as $handler) {
            $model = $handler->handle($throwable);

            if ($model instanceof RemoteRequestFailureModel) {
                return $this->createEntityFromModel($model);
            }
        }

        return null;
    }

    private function createEntityFromModel(RemoteRequestFailureModel $model): RemoteRequestFailureEntity
    {
        $entity = $this->remoteRequestFailureRepository->find(
            RemoteRequestFailureEntity::generateId($model->type, $model->code, $model->message)
        );

        if ($entity instanceof RemoteRequestFailureEntity) {
            return $entity;
        }

        $entity = new RemoteRequestFailureEntity($model->type, $model->code, $model->message);
        $this->remoteRequestFailureRepository->save($entity);

        return $entity;
    }
}
