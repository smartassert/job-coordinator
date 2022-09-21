<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Repository\JobRepository;
use App\Tests\Services\ApplicationClient\Client;
use App\Tests\Services\ApplicationClient\ClientFactory;
use App\Tests\Services\AuthenticationConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTest extends WebTestCase
{
    protected static KernelBrowser $kernelBrowser;
    protected Client $applicationClient;
    protected static AuthenticationConfiguration $authenticationConfiguration;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$kernelBrowser = self::createClient();

        $authenticationConfiguration = self::getContainer()->get(AuthenticationConfiguration::class);
        \assert($authenticationConfiguration instanceof AuthenticationConfiguration);
        self::$authenticationConfiguration = $authenticationConfiguration;

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

        $this->applicationClient = $factory->create($this->getClientAdapter());
    }

    abstract protected function getClientAdapter(): ClientInterface;
}
