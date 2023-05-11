<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\JobController;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use App\Services\UlidFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\UsersSecurityBundle\Security\User;

class JobControllerTest extends TestCase
{
    private JobController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new JobController();
    }

    public function testCreateFailureUnableToGenerateJobId(): void
    {
        $ulidFactory = \Mockery::mock(UlidFactory::class);
        $ulidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        $response = $this->controller->create(
            new CreateJobRequest('suite id value', 600, []),
            new User((new UlidFactory())->create(), md5((string) rand())),
            \Mockery::mock(JobRepository::class),
            $ulidFactory,
            \Mockery::mock(EventDispatcherInterface::class),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertIsArray($responseData);
        self::assertEquals(
            [
                'type' => 'server_error',
                'message' => 'Generated job id is an empty string.',
            ],
            $responseData
        );
    }
}
