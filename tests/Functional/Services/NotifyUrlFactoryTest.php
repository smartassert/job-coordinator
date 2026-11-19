<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Enum\RemoteEventType;
use App\Services\NotifyUrlFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotifyUrlFactoryTest extends WebTestCase
{
    private NotifyUrlFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(NotifyUrlFactory::class);
        \assert($factory instanceof NotifyUrlFactory);
        $this->factory = $factory;
    }

    #[DataProvider('urlIsNonEmptyStringDataProvider')]
    public function testUrlIsNonEmptyString(RemoteEventType $remoteEventType): void
    {
        $url = $this->factory->create($remoteEventType);
        self::assertNotEmpty($url);
    }

    /**
     * @return array<mixed>
     */
    public static function urlIsNonEmptyStringDataProvider(): array
    {
        $dataSets = [];

        foreach (RemoteEventType::cases() as $remoteEventType) {
            $dataSets[$remoteEventType->value] = [
                'remoteEventType' => $remoteEventType,
            ];
        }

        return $dataSets;
    }
}
