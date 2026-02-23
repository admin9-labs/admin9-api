<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @scaffold — Health check endpoint.
 */
#[Group('Health', weight: -1)]
class HealthController extends Controller
{
    /**
     * Health check.
     *
     * @response array{status: string}
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $status = 'ok';
        } catch (\Throwable) {
            $status = 'error';
        }

        return $this->success([
            'status' => $status,
        ]);
    }
}
