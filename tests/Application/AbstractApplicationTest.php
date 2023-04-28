<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Repository\JobRepository;
use App\Tests\Services\ApplicationClient\Client;
use App\Tests\Services\ApplicationClient\ClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTest extends WebTestCase
{
    protected static KernelBrowser $kernelBrowser;
    protected Client $applicationClient;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$kernelBrowser = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        if ($jobRepository instanceof JobRepository) {
            foreach ($jobRepository->findAll() as $entity) {
                $entityManager->remove($entity);
                $entityManager->flush();
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(ClientFactory::class);
        \assert($factory instanceof ClientFactory);

        $this->applicationClient = $factory->create(static::getClientAdapter());
    }

    abstract protected static function getClientAdapter(): ClientInterface;
}
