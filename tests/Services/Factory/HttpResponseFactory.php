<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\WorkerManagerClient\Model\Machine;

class HttpResponseFactory
{
    public static function createForWorkerManagerMachine(Machine $machine): ResponseInterface
    {
        return new Response(200, ['content-type' => 'application/json'], (string) json_encode([
            'id' => $machine->id,
            'state' => $machine->state,
            'state_category' => $machine->stateCategory,
            'ip_addresses' => $machine->ipAddresses,
            'has_active_state' => $machine->hasActiveState,
            'has_ending_state' => $machine->hasEndingState,
            'meta_state' => [
                'ended' => $machine->metaState->ended,
                'succeeded' => $machine->metaState->succeeded,
                'pending' => $machine->metaState->pending,
            ],
        ]));
    }
}
