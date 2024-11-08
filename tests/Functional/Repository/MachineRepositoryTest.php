<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class MachineRepositoryTest extends WebTestCase
{
    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;
    }

    public function testHasDoesNotHave(): void
    {
        self::assertFalse($this->machineRepository->has((string) new Ulid()));
    }

    public function testHasDoesHave(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $machine = new Machine($jobId, 'up/active', 'active', false);
        $this->machineRepository->save($machine);

        self::assertTrue($this->machineRepository->has($jobId));
    }
}
