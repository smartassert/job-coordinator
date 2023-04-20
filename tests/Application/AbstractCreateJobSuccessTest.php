<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Message\MachineStateChangeCheckMessage;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\FileClient;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use SmartAssert\TestAuthenticationProviderBundle\UserProvider;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

abstract class AbstractCreateJobSuccessTest extends AbstractApplicationTest
{
    public function testCreateSuccess(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $userProvider = self::getContainer()->get(UserProvider::class);
        \assert($userProvider instanceof UserProvider);

        $sourceClient = self::getContainer()->get(SourceClient::class);
        \assert($sourceClient instanceof SourceClient);
        $source = $sourceClient->createFileSource($apiToken, md5((string) rand()));

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);
        $fileClient->add($apiToken, $source->getId(), 'test1.yaml', 'test 1 contents');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);
        $suite = $suiteClient->create($apiToken, $source->getId(), md5((string) rand()), ['test1.yaml']);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        $response = $this->applicationClient->makeCreateJobRequest($apiToken, $suite->getId());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        self::assertArrayHasKey('job', $responseData);
        $jobData = $responseData['job'];

        self::assertArrayHasKey('id', $jobData);
        self::assertTrue(Ulid::isValid($jobData['id']));

        self::assertArrayHasKey('suite_id', $jobData);
        self::assertSame($suite->getId(), $jobData['suite_id']);

        self::assertArrayHasKey('serialized_suite_id', $jobData);
        $serializedSuiteId = $jobData['serialized_suite_id'];

        self::assertArrayHasKey('machine', $responseData);
        $machineData = $responseData['machine'];

        self::assertArrayHasKey('id', $machineData);
        self::assertSame($jobData['id'], $machineData['id']);

        self::assertArrayHasKey('state', $machineData);
        self::assertSame('create/received', $machineData['state']);

        self::assertArrayHasKey('ip_addresses', $machineData);
        self::assertSame([], $machineData['ip_addresses']);

        $jobs = $jobRepository->findAll();
        self::assertCount(1, $jobs);

        $job = $jobs[0];
        self::assertInstanceOf(Job::class, $job);
        self::assertSame($job->userId, $userProvider->get('user@example.com')->id);
        self::assertSame($job->suiteId, $suite->getId());
        self::assertNotNull($job->resultsToken);

        $serializedSuiteClient = self::getContainer()->get(SerializedSuiteClient::class);
        \assert($serializedSuiteClient instanceof SerializedSuiteClient);

        $serializedSuite = $serializedSuiteClient->get($apiToken, $serializedSuiteId);

        self::assertSame($serializedSuiteId, $serializedSuite->getId());
        self::assertSame($suite->getId(), $serializedSuite->getSuiteId());

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $envelopes = $messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);

        $expectedMachineStateChangeCheckMessage = new MachineStateChangeCheckMessage(
            $apiToken,
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
