<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Tests\Model\RemoteEventConfiguration;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Server\HeadersConfigurator;
use Symfony\Component\Webhook\Server\HeaderSignatureConfigurator;
use Symfony\Component\Webhook\Server\JsonBodyConfigurator;

readonly class RemoteEventConfigurationFactory
{
    public function __construct(
        private HeadersConfigurator $headersConfigurator,
        private JsonBodyConfigurator $jsonBodyConfigurator,
        private HeaderSignatureConfigurator $headerSignatureConfigurator,
    ) {}

    public function create(RemoteEvent $event, string $secret): RemoteEventConfiguration
    {
        $options = new HttpOptions();

        $this->headersConfigurator->configure($event, $secret, $options);
        $this->jsonBodyConfigurator->configure($event, $secret, $options);
        $this->headerSignatureConfigurator->configure($event, $secret, $options);

        $data = $options->toArray();

        $headers = $data['headers'] ?? [];
        $headers = is_array($headers) ? $headers : [];

        $filteredHeaders = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            $filteredHeaders[strtolower($name)] = $value;
        }

        $body = $data['body'] ?? '';
        $body = is_string($body) ? $body : '';

        return new RemoteEventConfiguration($filteredHeaders, $body);
    }
}
