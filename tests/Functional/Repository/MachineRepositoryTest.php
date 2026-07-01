<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use App\Tests\Services\Generator\Id;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        self::assertFalse($this->machineRepository->has(Id::generate()));
    }

    public function testHasDoesHave(): void
    {
        $jobId = Id::generate();

        $machine = new Machine($jobId, 'up/active', 'active');
        $this->machineRepository->save($machine);

        self::assertTrue($this->machineRepository->has($jobId));
    }
}
