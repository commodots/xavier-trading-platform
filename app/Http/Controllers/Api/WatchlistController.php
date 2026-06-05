<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    // Get all items for the logged-in user
    public function index()
    {
        $watchlist = Watchlist::where('user_id', Auth::id())->latest()->get()
            ->map(fn ($item) => $this->normalizeWatchlistItem($item));

        return response()->json(['success' => true, 'data' => $watchlist]);
    }

    // Add a new item
    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'name' => 'required|string',
            'market' => 'required|string',
            'currency' => 'required|string',
            'added_price' => 'required|numeric',
        ]);

        
        $normalized_market = $this->normalizeMarket($validated['market']);
        $validated['market'] = $normalized_market;

        $item = Watchlist::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'symbol' => $validated['symbol'],
                'market' => $validated['market'],
            ],
            $validated
        );

        return response()->json(['success' => true, 'data' => $this->normalizeWatchlistItem($item)]);
    }

    // Remove an item
    public function destroy($id)
    {
        $item = Watchlist::where('user_id', Auth::id())->where('id', $id)->first();

        if ($item) {
            $item->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Not found'], 404);
    }

    /**
     * Normalize market value to standard categories: US, UK, NGX, CRYPTO
     */
    private function normalizeMarket(?string $market): string
    {
        $market = strtoupper(trim((string) ($market ?? '')));

        if (in_array($market, ['NASDAQ', 'NYSE', 'US', 'GLOBAL', 'INTERNATIONAL'], true)) {
            return 'US';
        }

        if (in_array($market, ['LSE', 'LONDON', 'UK'], true)) {
            return 'UK';
        }

        if (in_array($market, ['NGX', 'LOCAL', 'NIGERIA'], true)) {
            return 'NGX';
        }

        if ($market === 'CRYPTO' || str_contains($market, 'USDT')) {
            return 'CRYPTO';
        }

        return $market ?: 'US';
    }

    /**
     * Transform watchlist item to ensure normalized market and include all required fields
     */
    private function normalizeWatchlistItem($item): array
    {
        return [
            'id' => $item->id,
            'symbol' => $item->symbol,
            'name' => $item->name,
            'market' => $this->normalizeMarket($item->market),
            'currency' => $item->currency,
            'added_price' => (float) $item->added_price,
            'created_at' => $item->created_at,
        ];
    }
}
