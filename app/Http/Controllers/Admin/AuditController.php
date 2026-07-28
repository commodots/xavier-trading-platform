<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionAudit;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = TransactionAudit::with('user');

        // Filter by date
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        // Filter by entity type
        if ($request->has('module')) {
            $query->where('entity_type', $request->module);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $audits = $query->orderBy('created_at', 'desc')->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $audits
        ]);
    }

    public function export(Request $request)
    {
        // Export logic for audit logs
        $audits = TransactionAudit::with('user')->get();

        // Implement export to CSV/Excel
        // This is a placeholder
        return response()->json([
            'success' => true,
            'message' => 'Export initiated',
            'count' => $audits->count()
        ]);
    }
}