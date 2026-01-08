<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use App\Models\ServiceConnection;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($id) {
            // Deactivate all services
            Service::query()->update(['is_active' => false]);

            // Activate the specified service
            Service::findOrFail($id)->update(['is_active' => true]);
        });

        return response()->json(['success' => true]);
    }
}
