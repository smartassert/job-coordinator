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

        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $label = (string) new Ulid();
        \assert('' !== $label);

        $job = new Job($userId, $suiteId, $label);

        $entityManager->persist($job);
        $entityManager->flush();

        $jobId = $job->getId();

        $entityManager->clear();

        $retrievedJob = $entityManager->find(Job::class, $jobId);
        self::assertInstanceOf(Job::class, $retrievedJob);
        self::assertSame($userId, $retrievedJob->getUserId());
        self::assertSame($suiteId, $retrievedJob->getSuiteId());
        self::assertSame($label, $retrievedJob->getLabel());
    }
}
