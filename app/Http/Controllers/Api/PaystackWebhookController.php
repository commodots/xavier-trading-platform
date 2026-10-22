<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        return app(PaystackController::class)->callback($request);
    }
}
