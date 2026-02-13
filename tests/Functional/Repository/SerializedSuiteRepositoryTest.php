<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\SerializedSuite;
use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class SerializedSuiteRepositoryTest extends WebTestCase
{
    private SerializedSuiteRepository $serializedSuiteRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        $this->serializedSuiteRepository = $serializedSuiteRepository;
    }

    public function testHasDoesNotHave(): void
    {
        self::assertFalse($this->serializedSuiteRepository->has((string) new Ulid()));
    }

    public function testHasDoesHave(): void
    {
        $jobId = (string) new Ulid();
        $serializedSuiteId = (string) new Ulid();

        $serializedSuite = new SerializedSuite(
            $jobId,
            $serializedSuiteId,
            'preparing',
            false,
            false,
            new MetaState(false, false),
        );
        $this->serializedSuiteRepository->save($serializedSuite);

        self::assertTrue($this->serializedSuiteRepository->has($jobId));
    }
}
