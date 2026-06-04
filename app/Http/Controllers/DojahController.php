<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DojahService;
use Illuminate\Http\Request;

class DojahController extends Controller
{
    public function __construct(private DojahService $dojah) {}

    public function verifyLiveness(Request $request)
    {
        $request->validate(['image' => 'required|string']);

        $image = $request->image;
        if (str_contains($image, 'base64,')) {
            $image = substr($image, strpos($image, 'base64,') + 7);
        }

        $result     = $this->dojah->checkLiveness($image);
        $confidence = $result['entity']['confidence'] ?? 0;

        if (!($result['success'] ?? false) || $confidence < 70) {
            return response()->json([
                'success'    => false,
                'message'    => 'Liveness check failed. Please try again in good lighting.',
                'confidence' => $confidence,
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Liveness verification successful.',
            'confidence' => $confidence,
        ]);
    }
}
