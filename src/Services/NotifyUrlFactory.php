<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteEventName;
use Symfony\Component\Routing\RouterInterface;

readonly class NotifyUrlFactory
{
    public function __construct(
        private RouterInterface $router,
    ) {}

    /**
     * @return ?non-empty-string
     */
    public function create(RemoteEventName $eventType): ?string
    {
        $url = $this->router->generate('_webhook_controller', ['type' => $eventType->value]);
        if ('' === $url) {
            return null;
        }

        return $url;
    }
}
