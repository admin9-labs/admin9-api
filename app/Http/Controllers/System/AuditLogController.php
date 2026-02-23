<?php

namespace App\Http\Controllers\System;

use App\Filters\AuditLogFilter;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * @scaffold — Audit log query + cleanup.
 */
#[Group('Audit Logs', weight: 5)]
class AuditLogController extends Controller
{
    /**
     * List audit logs.
     */
    public function index(): JsonResponse
    {
        $logs = AuditLog::query()
            ->filter(AuditLogFilter::class)
            ->with('causer:id,name,email')
            ->latest()
            ->paginate();

        return $this->success($logs);
    }

    /**
     * Get audit log detail.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('causer:id,name,email');

        return $this->success($auditLog);
    }
}
