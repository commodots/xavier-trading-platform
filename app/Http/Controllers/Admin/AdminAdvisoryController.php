<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryPost;
use Illuminate\Http\Request;

class AdminAdvisoryController extends Controller
{
    public function index()
    {
        return response()->json(AdvisoryPost::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'content' => 'required|string',
            'market_type' => 'required|in:local,international,crypto',
            'risk_level' => 'required|in:low,medium,high',
            'is_premium' => 'boolean'
        ]);

        $post = AdvisoryPost::create($validated);

        return response()->json(['message' => 'Advisory post published!', 'data' => $post]);
    }

    public function destroy($id)
    {
        AdvisoryPost::findOrFail($id)->delete();
        return response()->json(['message' => 'Post deleted successfully']);
    }
}