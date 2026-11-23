<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\WorkerManagerMachineStateChangedWebhookConsumer;

use App\Entity\Machine;
use App\Enum\RemoteEventType;
use App\Event\MachineRetrievedEvent;
use App\Model\Machine\Machine as MachineModel;
use App\Model\MetaState;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Generator\StringValue;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class SuccessTest extends AbstractConsumerTestCase
{
    public function testSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $machineModel = new MachineModel(
            $job->getId(),
            StringValue::random(),
            StringValue::random(),
            null,
            null,
            new MetaState(
                false,
                false,
                false,
            ),
            false,
            false,
            false,
        );

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineEntity = new Machine(
            $job->getId(),
            StringValue::random(),
            StringValue::random(),
        );
        $machineRepository->save($machineEntity);

        $machineModelData = [
            'id' => $machineModel->getId(),
            'state' => $machineModel->getState(),
            'ip_addresses' => [],
            'state_category' => $machineModel->getStateCategory(),
            'action_failure' => null,
            'has_active_state' => $machineModel->isActive(),
            'has_ending_state' => $machineModel->isEnding(),
            'meta_state' => [
                'pending' => $machineModel->getMetaState()->pending,
                'ended' => $machineModel->getMetaState()->ended,
                'succeeded' => $machineModel->getMetaState()->succeeded,
            ],
            'previous_states' => [],
        ];

        $event = new RemoteEvent(
            name: RemoteEventType::MACHINE_STATE_CHANGED->value,
            id: (string) new Ulid(),
            payload: $machineModelData,
        );

        $response = self::$staticApplicationClient->makeWorkerManagerMachineStateChangedNotifyRequest(
            $this->remoteEventRequestFactory->createHeaders($event, $this->notifySecret),
            $this->remoteEventRequestFactory->createBody($event, $this->notifySecret),
        );

        self::assertSame(202, $response->getStatusCode());

        self::assertEquals(
            [
                new MachineRetrievedEvent($machineModel),
            ],
            $this->eventRecorder->all(MachineRetrievedEvent::class),
        );
    }
}
