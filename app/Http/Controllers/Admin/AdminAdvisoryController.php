<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryPost;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\NewAdvisoryNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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

        if ($post->is_premium) {
            $activeSubscriberIds = UserSubscription::where('expires_at', '>', now())->pluck('user_id');

            $users = User::whereIn('id', $activeSubscriberIds)->get();
        } else {
            // Send to everyone
            $users = User::all();
        }

        Notification::send($users, new NewAdvisoryNotification($post));

        return response()->json(['message' => 'Advisory post published!', 'data' => $post]);
    }

    public function destroy($id)
    {
        AdvisoryPost::findOrFail($id)->delete();
        return response()->json(['message' => 'Post deleted successfully']);
    }
}