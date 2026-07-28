<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceSession;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    /**
     * List all active devices for the user
     */
    public function index(Request $request): JsonResponse
    {
        $devices = UserDeviceSession::where('user_id', $request->user()->id)
            ->active()
            ->orderByDesc('last_used_at')
            ->get();

        return response()->json([
            'devices' => $devices,
            'total' => $devices->count(),
        ]);
    }

    /**
     * Register/track current device
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|in:desktop,mobile,tablet',
            'browser' => 'nullable|string',
            'os' => 'nullable|string',
        ]);

        $user = $request->user();

        // Check if device already exists
        $device = UserDeviceSession::where('user_id', $user->id)
            ->where('ip_address', $request->ip())
            ->where('device_type', $request->device_type)
            ->first();

        if (!$device) {
            $device = UserDeviceSession::create([
                'user_id' => $user->id,
                'device_name' => $request->device_name,
                'device_type' => $request->device_type,
                'browser' => $request->browser,
                'os' => $request->os,
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
            ]);

            AuditService::logSecurityEvent(
                $user,
                'device_registered',
                "New device registered: {$request->device_name}",
                ['device_id' => $device->id]
            );
        } else {
            $device->markAsUsed();
        }

        return response()->json([
            'device' => $device,
            'message' => 'Device registered successfully.',
        ]);
    }

    /**
     * Trust/mark device as trusted
     */
    public function trust(Request $request, UserDeviceSession $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $device->trust();

        AuditService::logSecurityEvent(
            $request->user(),
            'device_trusted',
            "Device marked as trusted: {$device->device_name}",
            ['device_id' => $device->id]
        );

        return response()->json([
            'device' => $device,
            'message' => 'Device marked as trusted.',
        ]);
    }

    /**
     * Revoke/remove device
     */
    public function revoke(Request $request, UserDeviceSession $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $deviceName = $device->device_name;
        $device->revoke();

        AuditService::logSecurityEvent(
            $request->user(),
            'device_revoked',
            "Device revoked: {$deviceName}",
            ['device_id' => $device->id]
        );

        return response()->json([
            'message' => 'Device has been revoked.',
        ]);
    }
}
