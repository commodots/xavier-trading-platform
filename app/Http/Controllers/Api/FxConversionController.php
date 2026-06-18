<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Fx\FxConversionService;
use Illuminate\Http\Request;

class FxConversionController extends Controller
{
    public function __construct(
        protected FxConversionService $fxConversionService
    ) {}

    public function quote(Request $request)
    {
        $request->validate([
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'amount' => 'required|numeric|gt:0',
        ]);

        try {
            $result = $this->fxConversionService->quote(
                auth()->id(),
                strtoupper($request->from_currency),
                strtoupper($request->to_currency),
                (float) $request->amount
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function convert(Request $request)
    {
        $request->validate([
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'amount' => 'required|numeric|gt:0',
        ]);

        try {
            $result = $this->fxConversionService->convert(
                auth()->id(),
                strtoupper($request->from_currency),
                strtoupper($request->to_currency),
                (float) $request->amount
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function history(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $history = $this->fxConversionService->getHistory(
            auth()->id(),
            $request->limit ?? 20
        );

        return response()->json(['success' => true, 'data' => $history]);
    }
}
