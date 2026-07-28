<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get audit logs for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 50);
        $type = $request->input('type');

        $logs = AuditService::getLogsForUser($user, $type, $limit);

        return response()->json([
            'logs' => $logs,
            'total' => $logs->count(),
        ]);
    }

    /**
     * Get audit log summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = AuditService::getLogsForUser($user, null, 100);

        $summary = [
            'total_events' => $logs->count(),
            'security_events' => $logs->where('event_type', 'login')->count(),
            'financial_events' => $logs->where('event_type', 'financial_transaction')->count(),
            'settings_changes' => $logs->where('event_type', 'settings_change')->count(),
            'recent_activity' => $logs->take(5),
        ];

        return response()->json($summary);
    }
}
