<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'category', 'vendor', 'currency', 'from', 'to']);

        $query = Expense::with(['category', 'vendor', 'requester']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('expense_no', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%")
                    ->orWhere('invoice_number', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('expense_category_id', $filters['category']);
        }

        if (!empty($filters['vendor'])) {
            $query->where('vendor_id', $filters['vendor']);
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }

        if (!empty($filters['from'])) {
            $query->whereDate('expense_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('expense_date', '<=', $filters['to']);
        }

        $expenses = $query->latest()->paginate(20);

        return response()->json($expenses);
    }

    public function create()
    {
        return response()->json(['message' => 'Expense creation form ready']);
    }

    public function edit(Expense $expense)
    {
        return response()->json($expense);
    }

    public function categories()
    {
        return response()->json(
            ExpenseCategory::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'code'])
                ->get()
        );
    }

    public function vendors()
    {
        return response()->json(
            Vendor::query()
                ->where('is_active', true)
                ->select(['id', 'name'])
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,card,wallet,other',
            'reference' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'description' => 'required|string',
            'status' => 'required|in:draft,approved,paid,cancelled',
        ]);

        $validated['currency'] = strtoupper($validated['currency'] ?? 'NGN');
        $validated['requested_by'] = $request->user()?->id;
        $validated['expense_no'] = Expense::generate();

        $expense = Expense::create($validated);

        return response()->json($expense, 201);
    }

    public function show(Expense $expense)
    {
        $expense->load(['category', 'vendor', 'requester']);

        return response()->json($expense);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,card,wallet,other',
            'reference' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'description' => 'required|string',
            'status' => 'required|in:draft,approved,paid,cancelled',
        ]);

        $validated['currency'] = strtoupper($validated['currency'] ?? 'NGN');
        $expense->update($validated);

        return response()->json($expense);
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json(null, 204);
    }
}