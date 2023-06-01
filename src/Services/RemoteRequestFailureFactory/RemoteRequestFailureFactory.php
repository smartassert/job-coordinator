<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Exception\EmptyUlidException;
use App\Model\RemoteRequestFailure as RemoteRequestFailureModel;
use App\Repository\RemoteRequestFailureRepository;
use App\Services\UlidFactory;

class RemoteRequestFailureFactory
{
    /**
     * @param iterable<ExceptionHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
        private readonly RemoteRequestFailureRepository $remoteRequestFailureRepository,
        private readonly UlidFactory $ulidFactory,
    ) {
    }

    public function create(\Throwable $throwable): ?RemoteRequestFailureEntity
    {
        foreach ($this->handlers as $handler) {
            $model = $handler->handle($throwable);

            if ($model instanceof RemoteRequestFailureModel) {
                try {
                    return $this->createEntityFromModel($model);
                } catch (EmptyUlidException) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * @throws EmptyUlidException
     */
    private function createEntityFromModel(RemoteRequestFailureModel $model): RemoteRequestFailureEntity
    {
        $entity = $this->remoteRequestFailureRepository->findOneBy([
            'type' => $model->type,
            'code' => $model->code,
            'message' => $model->message,
        ]);

        if ($entity instanceof RemoteRequestFailureEntity) {
            return $entity;
        }

        $entity = new RemoteRequestFailureEntity(
            $this->ulidFactory->create(),
            $model->type,
            $model->code,
            $model->message
        );

        $this->remoteRequestFailureRepository->save($entity);

        return $entity;
    }
}
