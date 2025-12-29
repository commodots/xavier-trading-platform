<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use App\Models\ServiceConnection;
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
        return response()->json($service, 201);
    }

    public function addConnection(Request $request, $serviceId)
    {
        // Validation for connection details
        $request->validate([
            'mode' => 'required|in:live,testing,dummy',
            'base_url' => 'required|url|max:255',
            'headers' => 'nullable|array',
            'parameters' => 'nullable|array',
            'credentials' => 'nullable|array',
        ]);

        Service::findOrFail($serviceId);

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
        return response()->json(['success' => true], 201);
    }

    public function toggleService($id)
    {
        $service = Service::findOrFail($id);
        // Switch between true and false
        $service->is_active = !$service->is_active;
        $service->save();

        return response()->json([
            'success' => true,
            'is_active' => $service->is_active,
            'message' => $service->name . ($service->is_active ? ' enabled' : ' disabled')
        ]);
    }
    public function updateMode(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->update(['mode' => $request->mode]);
        return response()->json(['message' => 'Mode updated']);
    }
}
