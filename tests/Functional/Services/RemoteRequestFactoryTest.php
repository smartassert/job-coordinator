<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestFactoryTest extends WebTestCase
{
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFactory $remoteRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->remoteRequestRepository = $remoteRequestRepository;

        $remoteRequestFactory = self::getContainer()->get(RemoteRequestFactory::class);
        \assert($remoteRequestFactory instanceof RemoteRequestFactory);
        $this->remoteRequestFactory = $remoteRequestFactory;
    }

    public function testCreateNoExistingEntity(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $jobId = md5((string) rand());
        $type = RemoteRequestType::RESULTS_CREATE;

        $remoteRequest = $this->remoteRequestFactory->create($jobId, $type);

        $remoteRequestReflector = new \ReflectionClass($remoteRequest);
        $idProperty = $remoteRequestReflector->getProperty('id');
        $remoteRequestId = $idProperty->getValue($remoteRequest);
        \assert(is_string($remoteRequestId) && '' !== $remoteRequestId);

        self::assertEquals(new RemoteRequest($remoteRequestId, $jobId, $type), $remoteRequest);
        self::assertSame(1, $this->remoteRequestRepository->count([]));
    }

    public function testCreateHasExistingEntity(): void
    {
        self::assertSame(0, $this->remoteRequestRepository->count([]));

        $jobId = md5((string) rand());
        $type = RemoteRequestType::RESULTS_CREATE;

        $firstRemoteRequest = $this->remoteRequestFactory->create($jobId, $type);
        $secondRemoteRequest = $this->remoteRequestFactory->create($jobId, $type);

        self::assertSame(1, $this->remoteRequestRepository->count([]));
        self::assertSame($firstRemoteRequest, $secondRemoteRequest);
    }
}
