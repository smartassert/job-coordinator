<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Model\Machine\Machine;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class HttpResponseFactory
{
    public static function createForMachine(Machine $machine): ResponseInterface
    {
        return new Response(200, ['content-type' => 'application/json'], (string) json_encode([
            'id' => $machine->getId(),
            'state' => $machine->getState(),
            'state_category' => $machine->getStateCategory(),
            'ip_addresses' => [
                $machine->getIpAddress(),
            ],
            'has_active_state' => $machine->isActive(),
            'has_ending_state' => $machine->isEnding(),
            'meta_state' => [
                'ended' => $machine->getMetaState()->ended,
                'succeeded' => $machine->getMetaState()->succeeded,
                'pending' => $machine->getMetaState()->pending,
            ],
        ]));
    }
}
