<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Message\MachineStateChangeCheckMessage;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

abstract class AbstractCreateJobSuccessTest extends AbstractApplicationTest
{
    public function testCreateSuccess(): void
    {
        $suiteId = (string) new Ulid();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        $response = $this->applicationClient->makeCreateJobRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $suiteId,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        self::assertArrayHasKey('job', $responseData);
        $jobData = $responseData['job'];

        self::assertArrayHasKey('id', $jobData);
        self::assertTrue(Ulid::isValid($jobData['id']));

        self::assertArrayHasKey('suite_id', $jobData);
        self::assertSame($suiteId, $jobData['suite_id']);

        self::assertArrayHasKey('machine', $responseData);
        $machineData = $responseData['machine'];

        self::assertArrayHasKey('id', $machineData);
        self::assertSame($jobData['id'], $machineData['id']);

        self::assertArrayHasKey('state', $machineData);
        self::assertSame('create/received', $machineData['state']);

        self::assertArrayHasKey('state_category', $machineData);
        self::assertSame('pre_active', $machineData['state_category']);

        self::assertArrayHasKey('ip_addresses', $machineData);
        self::assertSame([], $machineData['ip_addresses']);

        $jobs = $jobRepository->findAll();
        self::assertCount(1, $jobs);

        $job = $jobs[0];
        self::assertInstanceOf(Job::class, $job);
        self::assertSame($job->userId, self::$authenticationConfiguration->getUser()->id);
        self::assertSame($job->suiteId, $suiteId);
        self::assertNotNull($job->resultsToken);

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $envelopes = $messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);

        $expectedMachineStateChangeCheckMessage = new MachineStateChangeCheckMessage(
            self::$authenticationConfiguration->getValidApiToken(),
            new Machine(
                $machineData['id'],
                $machineData['state'],
                $machineData['state_category'],
                $machineData['ip_addresses']
            )
        );

        self::assertEquals($expectedMachineStateChangeCheckMessage, $envelope->getMessage());
    }
}
