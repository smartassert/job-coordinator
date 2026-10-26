<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Machine\Machine;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\ServiceClient\Exception\UnauthorizedException;
use SmartAssert\WorkerManagerClient\Client;
use SmartAssert\WorkerManagerClient\Exception\CreateMachineException;

readonly class WorkerManagerClientWrapper
{
    public function __construct(
        private Client $client,
        private MachineModelFactory $machineModelFactory,
    ) {}

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $machineId
     * @param non-empty-string $notifyUrl
     *
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws InvalidResponseTypeException
     * @throws NonSuccessResponseException
     * @throws UnauthorizedException
     * @throws CreateMachineException
     */
    public function create(string $authenticationToken, string $machineId, string $notifyUrl): Machine
    {
        return $this->machineModelFactory->createFromWorkerManagerClientMachine(
            $this->client->createMachine($authenticationToken, $machineId, $notifyUrl),
        );
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $machineId
     *
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws InvalidResponseTypeException
     * @throws NonSuccessResponseException
     * @throws UnauthorizedException
     */
    public function get(string $authenticationToken, string $machineId): Machine
    {
        return $this->machineModelFactory->createFromWorkerManagerClientMachine(
            $this->client->getMachine($authenticationToken, $machineId),
        );
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $machineId
     *
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws InvalidResponseTypeException
     * @throws NonSuccessResponseException
     * @throws UnauthorizedException
     */
    public function delete(string $authenticationToken, string $machineId): Machine
    {
        return $this->machineModelFactory->createFromWorkerManagerClientMachine(
            $this->client->deleteMachine($authenticationToken, $machineId),
        );
    }
}
