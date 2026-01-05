<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use App\Models\ServiceConnection;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdminServiceController extends Controller
{
    public function index()
    {
        return response()->json([
            'services' => Service::with('connections')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50|unique:services,type|regex:/^[a-z0-9_]+$/',
        ]);

        $service = Service::create($request->only('name', 'type'));
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Created',
                'details'    => "Created a new system service: {$service->name} (Type: {$service->type})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json($service, 201);
    }

    public function addConnection(Request $request, $serviceId)
    {
        $request->validate([
            'mode' => 'required|in:live,testing,dummy',
            'base_url' => 'required|url|max:255',
            'headers' => 'nullable|array',
            'parameters' => 'nullable|array',
            'credentials' => 'nullable|array',
        ]);

        $service = Service::findOrFail($serviceId);

        DB::transaction(function () use ($request, $serviceId) {
            // Deactivate all connections for the specific service and mode
            ServiceConnection::where('service_id', $serviceId)
                ->where('mode', $request->mode)
                ->update(['is_active' => false]);

            ServiceConnection::create([
                'service_id' => $serviceId,
                'mode' => $request->mode,
                'base_url' => $request->base_url,
                'headers' => $request->headers,
                'parameters' => $request->parameters,
                'credentials' => $request->credentials,
                'is_active' => true,
            ]);
        });
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Connection Update',
                'details'    => "Updated connection settings for {$service->name}. Mode: {$request->mode}, URL: {$request->base_url}. (Credentials updated)",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }
        return response()->json(['success' => true], 201);
    }

    public function toggleService($id)
    {
        $service = Service::findOrFail($id);
        $old = $service->is_active;
        $service->is_active = !$service->is_active;
        $service->save();

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Toggle Service',
                'details'    => "Changed {$service->name} status from " . ($old ? 'Active' : 'Inactive') . " to " . ($service->is_active ? 'Active' : 'Inactive'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'is_active' => $service->is_active,
            'message' => $service->name . ($service->is_active ? ' enabled' : ' disabled')
        ]);
    }
    public function updateMode(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $oldMode = $service->mode;
        $service->update(['mode' => $request->mode]);

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Mode Update',
                'details'    => "Switched {$service->name} environment mode from [{$oldMode}] to [{$request->mode}]",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}
        
        return response()->json(['message' => 'Mode updated']);
    }
}
