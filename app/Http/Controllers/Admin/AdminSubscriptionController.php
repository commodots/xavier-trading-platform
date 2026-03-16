<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function index()
    {
        return response()->json(SubscriptionPlan::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'tier' => 'required|in:regular,premium',
            'price' => 'required|numeric',
            'duration_days' => 'required|integer',
            'features' => 'nullable|string',
            'paystack_plan_code' => 'nullable|string' // From the Paystack dashboard
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json(['message' => 'Plan created successfully', 'data' => $plan]);
    }

    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'tier' => 'sometimes|in:regular,premium',
            'price' => 'sometimes|numeric',
            'duration_days' => 'sometimes|integer',
            'features' => 'nullable|string',
            'paystack_plan_code' => 'nullable|string'
        ]);

        $plan->update($validated);

        return response()->json(['message' => 'Plan updated successfully', 'data' => $plan]);
    }
   public function destroy($id)
    {
        //Find the exact plan by its ID, then delete it
        SubscriptionPlan::findOrFail($id)->delete();

        return response()->json(['message' => 'Plan deleted successfully']);
    }
}