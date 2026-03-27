<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModelPortfolio;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminModelPortfolioController extends Controller
{
    public function index()
    {
        return response()->json(ModelPortfolio::with('stocks')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'risk_profile' => 'required|in:conservative,balanced,aggressive',
            'starting_value' => 'required|numeric',
            'is_premium' => 'boolean',
            'stocks' => 'required|array|min:1',
            'stocks.*.symbol' => 'required|string',
            'stocks.*.allocation' => 'required|numeric|min:1|max:100',
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Create the parent portfolio
                $portfolio = ModelPortfolio::create([
                    'name' => $request->name,
                    'description' => $request->description,
                    'risk_profile' => $request->risk_profile,
                    'starting_value' => $request->starting_value,
                    'is_premium' => $request->is_premium ?? true,
                ]);

                // Loop through the submitted stocks and attach them
                foreach ($request->stocks as $stock) {
                    $portfolio->stocks()->create([
                        'symbol' => $stock['symbol'],
                        'allocation_percentage' => $stock['allocation'],
                    ]);
                }
            });

            return response()->json(['message' => 'Model Portfolio created successfully!']);

        } catch (Exception $e) {
            Log::error('Model portfolio create failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Failed to create portfolio.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'risk_profile' => 'required|in:conservative,balanced,aggressive',
            'starting_value' => 'required|numeric',
            'is_premium' => 'boolean',
            'stocks' => 'required|array|min:1',
            'stocks.*.symbol' => 'required|string',
            'stocks.*.allocation' => 'required|numeric|min:1|max:100',
        ]);

        try {
            $portfolio = ModelPortfolio::findOrFail($id);

            DB::transaction(function () use ($request, $portfolio) {
                $portfolio->update([
                    'name' => $request->name,
                    'description' => $request->description,
                    'risk_profile' => $request->risk_profile,
                    'starting_value' => $request->starting_value,
                    'is_premium' => $request->is_premium ?? true,
                ]);

                $portfolio->stocks()->delete();

                foreach ($request->stocks as $stock) {
                    $portfolio->stocks()->create([
                        'symbol' => $stock['symbol'],
                        'allocation_percentage' => $stock['allocation'],
                    ]);
                }
            });

            return response()->json(['message' => 'Model Portfolio updated successfully!']);

        } catch (Exception $e) {
            Log::error('Model portfolio update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Failed to update portfolio.'], 500);
        }
    }
}
