<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('System', 'System health and availability endpoints')]
class HealthCheckController extends Controller
{
    /**
     * Public API endpoint for quick backend availability checks.
     */
    #[Endpoint(
        operationId: 'apiHealth',
        title: 'Health check',
        description: 'Returns backend status information for monitoring and smoke checks.'
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
        ]);
    }
}
