<?php

declare(strict_types=1);

namespace App\Tests\Application;

use PHPUnit\Framework\Assert;

abstract class AbstractStatusTest extends AbstractApplicationTest
{
    public function testGetStatus(): void
    {
        $response = self::$staticApplicationClient->makeStatusRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsArray($responseData);

        $expectedResponseData = [
            'ready' => $this->getExpectedReadyValue(),
            'version' => $this->getExpectedVersion(),
        ];

        Assert::assertSame($expectedResponseData, $responseData);
    }

    abstract protected function getExpectedReadyValue(): bool;

    abstract protected function getExpectedVersion(): string;
}
