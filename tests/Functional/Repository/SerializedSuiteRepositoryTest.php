<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\SerializedSuite;
use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Generator\Id;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        self::assertFalse($this->serializedSuiteRepository->has(Id::generate()));
    }

    public function testHasDoesHave(): void
    {
        $jobId = Id::generate();
        $serializedSuiteId = Id::generate();

        $serializedSuite = new SerializedSuite(
            $jobId,
            $serializedSuiteId,
            'preparing',
            new MetaState(false, false, true),
        );
        $this->serializedSuiteRepository->save($serializedSuite);

        self::assertTrue($this->serializedSuiteRepository->has($jobId));
    }
}
