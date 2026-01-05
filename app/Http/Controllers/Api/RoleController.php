<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function toggleMode(Request $request)
    {
        $user = $request->user();
        $targetMode = $request->mode; 

        if ($targetMode === 'staff' && !$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'active_mode' => $targetMode,
            'user' => $user
        ]);
    }
}