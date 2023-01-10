<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class JobTest extends WebTestCase
{
    public function testEntityMapping(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $id = (string) new Ulid();
        \assert('' !== $id);

        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $resultToken = (string) new Ulid();
        \assert('' !== $resultToken);

        $job = new Job($id, $userId, $suiteId, $resultToken);

        $entityManager->persist($job);
        $entityManager->flush();

        $jobId = $job->id;

        $entityManager->clear();

        $retrievedJob = $entityManager->find(Job::class, $jobId);
        self::assertInstanceOf(Job::class, $retrievedJob);
        self::assertSame($id, $retrievedJob->id);
        self::assertSame($userId, $retrievedJob->userId);
        self::assertSame($suiteId, $retrievedJob->suiteId);
    }
}
