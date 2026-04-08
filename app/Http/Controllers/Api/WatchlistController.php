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
        $watchlist = Watchlist::where('user_id', Auth::id())->latest()->get();
        return response()->json($watchlist);
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

        $item = Watchlist::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'symbol' => $validated['symbol'],
                'market' => $validated['market']
            ],
            $validated
        );

        return response()->json(['success' => true, 'data' => $item]);
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
}