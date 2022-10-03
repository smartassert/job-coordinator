<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\JobController;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Services\UlidFactory;
use Monolog\Test\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

class JobControllerTest extends TestCase
{
    /**
     * @dataProvider createFailureDataProvider
     *
     * @param array<mixed> $expectedResponseData
     */
    public function testCreateFailure(
        Request $request,
        UserInterface $user,
        JobRepository $jobRepository,
        UlidFactory $ulidFactory,
        array $expectedResponseData,
    ): void {
        $controller = new JobController();

        $response = $controller->create($request, $user, $jobRepository, $ulidFactory);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertIsArray($responseData);
        self::assertEquals($expectedResponseData, $responseData);
    }

    /**
     * @return array<mixed>
     */
    public function createFailureDataProvider(): array
    {
        $ulidFactory = \Mockery::mock(UlidFactory::class);
        $ulidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        return [
            'empty user' => [
                'request' => \Mockery::mock(Request::class),
                'user' => (function (): UserInterface {
                    $user = \Mockery::mock(UserInterface::class);
                    $user
                        ->shouldReceive('getUserIdentifier')
                        ->andReturn('')
                    ;

                    return $user;
                })(),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => \Mockery::mock(UlidFactory::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'User identifier is empty.',
                ],
            ],
            'empty label generated' => [
                'request' => new Request(request: ['suite_id' => (string) new Ulid()]),
                'user' => (function (): UserInterface {
                    $user = \Mockery::mock(UserInterface::class);
                    $user
                        ->shouldReceive('getUserIdentifier')
                        ->andReturn((string) new Ulid())
                    ;

                    return $user;
                })(),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => (function (): UlidFactory {
                    $ulidFactory = \Mockery::mock(UlidFactory::class);
                    $ulidFactory
                        ->shouldReceive('create')
                        ->andThrow(new EmptyUlidException())
                    ;

                    return $ulidFactory;
                })(),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Generated job label is an empty string.',
                ],
            ],
        ];
    }
}
