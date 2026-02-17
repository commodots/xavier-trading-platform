<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use App\Models\ServiceConnection;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\StaffPermissionService;
use App\Models\ServiceConfig;

class AdminServiceController extends Controller
{
    public function index()
    {
        $services = Service::all()->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'type' => $service->type,
                'is_active' => $service->is_active,
                'created_at' => $service->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'services' => $services
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:ngx,crypto,stocks,fx,cscs,payment|unique:services,type',
            'is_active' => 'boolean',
        ]);

        $service = Service::create($data);

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Created',
                'details'    => "Created a new system service: {$service->name} (Type: {$service->type})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json($service, 201);
    }

    
    public function update(Request $request, $id)
    {
        // Check permissions
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Find the base Service, NOT ServiceConfig
        $service = Service::findOrFail($id);

        $data = $request->validate([
            'name'      => 'required|string',
            'type'      => 'required|in:ngx,crypto,stocks,fx,cscs,payment',
            'is_active' => 'boolean',
        ]);

        $service->update($data);

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Updated',
                'details'    => "Updated system service: {$service->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data' => $service
        ]);
    }

    public function addConnection(Request $request, $serviceId)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $request->validate([
            'mode' => 'required|in:live,testing,dummy',
            'base_url' => 'nullable|url|max:255', // Made nullable for dummy modes
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
                'headers' => empty($request->headers) ? null : $request->headers,
                'parameters' => empty($request->parameters) ? null : $request->parameters,
                'credentials' => empty($request->credentials) ? null : $request->credentials,
                'is_active' => true,
            ]);
        });

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Connection Update',
                'details'    => "Updated connection settings for {$service->name}. Mode: {$request->mode}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}

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

    public function getConnections($serviceId)
    {
        $service = Service::findOrFail($serviceId);
        $connections = ServiceConnection::where('service_id', $serviceId)->get();

        return response()->json([
            'success' => true,
            'connections' => $connections
        ]);
    }

    public function updateConnection(Request $request, $connectionId)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        $connection = ServiceConnection::findOrFail($connectionId);
        
        $data = $request->validate([
            'mode' => 'required|in:live,testing,dummy',
            'base_url' => 'nullable|url', // Nullable so dummy mode saves
            'headers' => 'nullable|array',
            'parameters' => 'nullable|array',
            'credentials' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Fix JSON saving bug by ensuring empty arrays save as null
        $data['headers'] = empty($data['headers']) ? null : $data['headers'];
        $data['parameters'] = empty($data['parameters']) ? null : $data['parameters'];
        $data['credentials'] = empty($data['credentials']) ? null : $data['credentials'];

        $connection->update($data);

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Service Connection Updated',
                'details'    => "Updated connection for {$connection->service->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'connection' => $connection
        ]);
    }

    public function getConfig($serviceId)
    {
        $service = Service::findOrFail($serviceId);
        $config = ServiceConfig::where('service', strtoupper($service->type))->first();

        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }

    public function updateConfig(Request $request, $serviceId)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        $service = Service::findOrFail($serviceId);
        
        // Use updateOrCreate in case the config record doesn't exist yet
        $config = ServiceConfig::updateOrCreate(
            ['service' => strtoupper($service->type)],
            [
                'params' => empty($request->params) ? null : $request->params,
                'is_active' => $request->is_active ?? true,
                'mode' => 'live' // Defaulting to live to fulfill DB constraints
            ]
        );

        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }
}