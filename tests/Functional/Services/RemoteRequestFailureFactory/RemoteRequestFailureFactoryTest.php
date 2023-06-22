<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\RemoteRequestFailureFactory;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Enum\RemoteRequestFailureType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use Psr\Http\Message\RequestInterface;
use SmartAssert\ServiceClient\Exception\CurlException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestFailureFactoryTest extends WebTestCase
{
    use RemoteRequestFailureCreationDataProviderTrait;

    private RemoteRequestFailureFactory $remoteRequestFailureFactory;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestFailureFactory = self::getContainer()->get(RemoteRequestFailureFactory::class);
        \assert($remoteRequestFailureFactory instanceof RemoteRequestFailureFactory);
        $this->remoteRequestFailureFactory = $remoteRequestFailureFactory;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $remoteRequestFailureRepository->remove($entity);
        }

        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;
    }

    /**
     * @dataProvider remoteRequestFailureCreationDataProvider
     */
    public function testCreate(
        \Throwable $throwable,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $remoteRequestFailure = $this->remoteRequestFailureFactory->create($throwable);

        self::assertInstanceOf(RemoteRequestFailureEntity::class, $remoteRequestFailure);

        $remoteRequestFailureData = $remoteRequestFailure->toArray();
        self::assertSame($expectedType->value, $remoteRequestFailureData['type']);
        self::assertSame($expectedCode, $remoteRequestFailureData['code']);
        self::assertSame($expectedMessage, $remoteRequestFailureData['message']);
    }

    public function testExistingEntityIsReturned(): void
    {
        $throwable = new CurlException(\Mockery::mock(RequestInterface::class), 28, 'timed out');

        $entity1 = $this->remoteRequestFailureFactory->create($throwable);
        $entity2 = $this->remoteRequestFailureFactory->create($throwable);

        self::assertSame($entity1, $entity2);
    }
}
