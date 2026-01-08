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
		$configs = ServiceConfig::orderBy('service')->get()->map(fn ($c) => [
			'id' => $c->id,
			'name' => strtoupper($c->service),
			'type' => $c->type,
			'enabled' => $c->is_active,
			'created_at' => $c->created_at,
		]);

		return response()->json([
			'success' => true,
			'data' => $configs
		]);
	}
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
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
	{
		$data = $request->validate([
			'service' => 'required|string',
			'type' => 'required|in:ngx,crypto,stocks,fx,cscs,payment',
			'mode' => 'required|in:live,test,dummy',
			'base_url' => 'nullable|string',
			'headers' => 'nullable|array',
			'params' => 'nullable|array',
			'credentials' => 'nullable|array',
			'is_active' => 'boolean',
		]);

		$config = ServiceConfig::create($data);

		return response()->json($config, 201);
	}

	public function update(Request $request, ServiceConfig $service)
	{
		$data = $request->validate([
			'service'     => 'required|string',
			'type'        => 'required|string',
			'mode'        => 'required|in:dummy,test,live',
			'base_url'    => 'nullable|string',
			'headers'     => 'nullable|array',
			'params'      => 'nullable|array',
			'credentials' => 'nullable|array',
			'is_active'   => 'boolean',
		]);

		$service->update($data);

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
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_services')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $service = Service::findOrFail($id);
        $old = $service->is_active;
        $service->is_active = !$service->is_active;
        $service->save();

        // If we just activated this service, deactivate all others (global toggle)
        if ($service->is_active) {
            Service::where('id', '!=', $service->id)->update(['is_active' => false]);
        }
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
