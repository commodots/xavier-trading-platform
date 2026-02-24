<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryPost;
use Illuminate\Http\Request;

class AdvisoryController extends Controller
{
    /**
     * Fetch free posts for non-subscribed users
     */
    public function freePosts()
    {
        $posts = AdvisoryPost::where('is_premium', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * Fetch premium insights for VIP subscribers
     */
    public function premiumPosts()
    {
        $posts = AdvisoryPost::where('is_premium', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $posts]);
    }
}