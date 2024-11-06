<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Services\ApplicationClient\Client;
use App\Tests\Services\ApplicationClient\ClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApplicationTest extends WebTestCase
{
    protected static KernelBrowser $kernelBrowser;
    protected static Client $staticApplicationClient;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!isset(static::$kernelBrowser)) {
            static::$kernelBrowser = self::createClient();
        }

        $factory = self::getContainer()->get(ClientFactory::class);
        \assert($factory instanceof ClientFactory);

        self::$staticApplicationClient = $factory->create(static::getClientAdapter());
    }

    public static function getClientAdapter(): ClientInterface
    {
        return \Mockery::mock(ClientInterface::class);
    }
}
