<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteEventType;
use Symfony\Component\Routing\RouterInterface;

readonly class NotifyUrlFactory
{
    public function __construct(
        private RouterInterface $router,
    ) {}

    /**
     * @return ?non-empty-string
     */
    public function create(RemoteEventType $eventType): ?string
    {
        $url = $this->router->generate('_webhook_controller', ['type' => $eventType->value]);
        if ('' === $url) {
            return null;
        }

        return $url;
    }
}
