<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceConfig;
use Illuminate\Http\Request;

class ServiceConfigController extends Controller
{
    public function update(Request $request)
    {
        ServiceConfig::updateOrCreate(
            ['service' => $request->service],
            [
                'mode' => $request->mode,
                'config' => $request->config,
            ]
        );

        return response()->json(['success' => true]);
    }
}
