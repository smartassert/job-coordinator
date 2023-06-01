<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\RemoteRequestFailureFactory;

use App\Entity\RemoteRequestFailure as RemoteRequestFailureEntity;
use App\Enum\RemoteRequestFailureType;
use App\Repository\RemoteRequestFailureRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SmartAssert\ServiceClient\Exception\CurlException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestFailureFactoryTest extends WebTestCase
{
    private RemoteRequestFailureFactory $remoteRequestFailureFactory;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestFailureFactory = self::getContainer()->get(RemoteRequestFailureFactory::class);
        \assert($remoteRequestFailureFactory instanceof RemoteRequestFailureFactory);
        $this->remoteRequestFailureFactory = $remoteRequestFailureFactory;

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;
    }

    /**
     * @dataProvider createDataProvider
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
        self::assertSame($expectedType, $remoteRequestFailure->type);
        self::assertSame($expectedCode, $remoteRequestFailure->code);
        self::assertSame($expectedMessage, $remoteRequestFailure->message);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $request = \Mockery::mock(RequestInterface::class);

        return [
            CurlException::class => [
                'throwable' => new CurlException($request, 28, 'timed out'),
                'expectedType' => RemoteRequestFailureType::NETWORK,
                'expectedCode' => 28,
                'expectedMessage' => 'timed out',
            ],
            NonSuccessResponseException::class => [
                'throwable' => new NonSuccessResponseException(
                    new Response(status: 503, reason: 'service unavailable'),
                ),
                'expectedType' => RemoteRequestFailureType::HTTP,
                'expectedCode' => 503,
                'expectedMessage' => 'service unavailable',
            ],
            ConnectException::class => [
                'throwable' => new ConnectException('network exception message', $request),
                'expectedType' => RemoteRequestFailureType::NETWORK,
                'expectedCode' => 0,
                'expectedMessage' => 'network exception message',
            ],
            \Exception::class => [
                'throwable' => new \Exception('generic exception message', 123),
                'expectedType' => RemoteRequestFailureType::UNKNOWN,
                'expectedCode' => 123,
                'expectedMessage' => 'generic exception message',
            ],
        ];
    }

    public function testExistingEntityIsReturned(): void
    {
        $throwable = new CurlException(\Mockery::mock(RequestInterface::class), 28, 'timed out');

        $entity1 = $this->remoteRequestFailureFactory->create($throwable);
        $entity2 = $this->remoteRequestFailureFactory->create($throwable);

        self::assertSame($entity1, $entity2);
    }
}
