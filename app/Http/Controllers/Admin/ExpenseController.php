<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Server-side expense permission enforcement.
     *
     * Front-end visibility alone is never trusted: every action re-checks
     * permissions here. Spatie granular permissions win when configured
     * (`view_expenses`, `create_expenses`, ...); staff whose accounts predate
     * them fall back to the same legacy-role mapping AdminMiddleware and
     * RolePermissionSeeder use. Customers never reach this controller — the
     * `admin` middleware already rejects them — so this protects specifically
     * against *unauthorized staff* performing write actions.
     */
    protected function ensureCan(string $permission): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        try {
            if ($user->hasRole('super-admin') || $user->can($permission)) {
                return;
            }
        } catch (\Throwable $e) {
            
        }

        $legacyRole = $user->role ?? null;

        if (in_array($legacyRole, ['admin', 'super-admin'], true)) {
            return;
        }

        if ($legacyRole === 'accounts' && in_array($permission, [
            'view_expenses',
            'create_expenses',
            'edit_expenses',
            'approve_expenses',
            'pay_expenses',
        ], true)) {
            return;
        }

        if ($permission === 'view_expenses' && in_array($legacyRole, ['staff', 'compliance', 'manager', 'support'], true)) {
            return;
        }

        if ($permission === 'manage_vendors' || $permission === 'manage_expense_categories') {
            // Management endpoints double as lookup sources for read-only pages.
            $readFallback = in_array($legacyRole, ['staff', 'compliance', 'manager', 'support', 'accounts'], true);

            if ($readFallback && str_starts_with(request()->getMethod(), 'GET')) {
                return;
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    /**
     * List expenses with filters and summary aggregates.
     */
    public function index(Request $request)
    {
        $this->ensureCan('view_expenses');

        $query = Expense::with(['category', 'vendor', 'department', 'requester']);

        if (! empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('expenses.expense_no', 'like', "%{$request->search}%")
                    ->orWhere('expenses.description', 'like', "%{$request->search}%")
                    ->orWhere('expenses.invoice_number', 'like', "%{$request->search}%")
                    ->orWhere('expenses.reference', 'like', "%{$request->search}%")
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', "%{$request->search}%"));
            });
        }

        if (! empty($request->status)) {
            $query->where('expenses.status', $request->status);
        }

        if (! empty($request->category)) {
            $query->where('expenses.expense_category_id', $request->category);
        }

        if (! empty($request->vendor)) {
            $query->where('expenses.vendor_id', $request->vendor);
        }

        if (! empty($request->department)) {
            $query->where('expenses.department_id', $request->department);
        }

        if (! empty($request->currency)) {
            $query->where('expenses.currency', strtoupper($request->currency));
        }

        if (! empty($request->from)) {
            $query->whereDate('expenses.expense_date', '>=', $request->from);
        }

        if (! empty($request->to)) {
            $query->whereDate('expenses.expense_date', '<=', $request->to);
        }

        // Summary aggregates (database-level, no loading all rows into Vue)
        $summary = (clone $query)->selectRaw('
                COALESCE(SUM(amount), 0) as total
            ')
            ->first();

        return response()->json([
            'expenses' => $query->latest('expenses.expense_date')->paginate(20),
            'summary' => [
                'total' => (float) $summary->total,
                'draft' => (float) (clone $query)->where('expenses.status', 'draft')->sum('amount'),
                'approved' => (float) (clone $query)->where('expenses.status', 'approved')->sum('amount'),
                'paid' => (float) (clone $query)->where('expenses.status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function create()
    {
        $this->ensureCan('create_expenses');

        return response()->json(['message' => 'Expense creation form ready']);
    }

    public function edit(Expense $expense)
    {
        $this->ensureCan('edit_expenses');

        abort_if($expense->status === 'paid', 422, 'Paid expenses cannot be edited.');

        $expense->load(['category', 'vendor', 'department']);

        return response()->json($expense);
    }

    public function categories()
    {
        $this->ensureCan('manage_expense_categories');

        return response()->json(
            ExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->select(['id', 'name', 'code'])
                ->get()
        );
    }

    public function vendors()
    {
        $this->ensureCan('manage_vendors');

        return response()->json(
            Vendor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->select(['id', 'name'])
                ->get()
        );
    }

    public function departments()
    {
        $this->ensureCan('view_expenses');

        return response()->json(
            Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->select(['id', 'name'])
                ->get()
        );
    }

    // Expense Category Management 

    public function indexCategories()
    {
        $this->ensureCan('manage_expense_categories');

        return response()->json(
            ExpenseCategory::query()
                ->withCount('expenses')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeCategory(Request $request)
    {
        $this->ensureCan('manage_expense_categories');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'code' => ['required', 'string', 'max:20', 'unique:expense_categories,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $data['is_active'] ?? true;

        $category = ExpenseCategory::create($data);

        AuditService::log('expense_category_created', $category, "Expense category '{$category->name}' created");

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $category)
    {
        $this->ensureCan('manage_expense_categories');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,'.$category->id],
            'code' => ['required', 'string', 'max:20', 'unique:expense_categories,code,'.$category->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        AuditService::log('expense_category_updated', $category->fresh(), "Expense category '{$category->name}' updated");

        return response()->json($category->fresh());
    }

    public function toggleCategory(ExpenseCategory $category)
    {
        $this->ensureCan('manage_expense_categories');

        $category->update(['is_active' => ! $category->is_active]);

        $state = $category->is_active ? 'activated' : 'deactivated';

        AuditService::log('expense_category_'.$state, $category->fresh(), "Expense category '{$category->name}' {$state}");

        return response()->json($category->fresh());
    }

    //  Vendor Management 

    public function indexVendors()
    {
        $this->ensureCan('manage_vendors');

        return response()->json(
            Vendor::query()
                ->withCount('expenses')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeVendor(Request $request)
    {
        $this->ensureCan('manage_vendors');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $data['is_active'] ?? true;

        $vendor = Vendor::create($data);

        AuditService::log('vendor_created', $vendor, "Vendor '{$vendor->name}' created");

        return response()->json($vendor, 201);
    }

    public function updateVendor(Request $request, Vendor $vendor)
    {
        $this->ensureCan('manage_vendors');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $vendor->update($data);

        AuditService::log('vendor_updated', $vendor->fresh(), "Vendor '{$vendor->name}' updated");

        return response()->json($vendor->fresh());
    }

    public function toggleVendor(Vendor $vendor)
    {
        $this->ensureCan('manage_vendors');

        $vendor->update(['is_active' => ! $vendor->is_active]);

        $state = $vendor->is_active ? 'activated' : 'deactivated';

        AuditService::log('vendor_'.$state, $vendor->fresh(), "Vendor '{$vendor->name}' {$state}");

        return response()->json($vendor->fresh());
    }

    /**
     * Create a new expense.
     *
     * The status is ALWAYS forced to 'draft' — the person entering an expense
     * must not be able to submit `status = paid` and instantly make it a paid
     * expense. Only the explicit approve / mark-paid / cancel actions below may
     * change the status.
     */
    public function store(Request $request)
    {
        $this->ensureCan('create_expenses');

        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,card,wallet,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['requested_by'] = auth()->id();
        $data['status'] = 'draft';

        $expense = Expense::create($data);

        // Safe, unique, human-readable number derived from the database ID.
        $expense = Expense::assignExpenseNo($expense);

        AuditService::log('expense_created', $expense, 'Expense created');

        return response()->json($expense->load(['category', 'vendor', 'department', 'requester']), 201);
    }

    public function show(Expense $expense)
    {
        $this->ensureCan('view_expenses');

        $expense->load(['category', 'vendor', 'department', 'requester']);

        return response()->json($expense);
    }

    /**
     * Update an expense.
     *
     * Paid expenses cannot be edited through the normal Edit screen — this
     * protects the integrity of the P&L.
     */
    public function update(Request $request, Expense $expense)
    {
        $this->ensureCan('edit_expenses');

        abort_if($expense->status === 'paid', 422, 'Paid expenses cannot be edited.');

        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,card,wallet,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $data['currency'] = strtoupper($data['currency']);

        $expense->update($data);

        AuditService::log('expense_updated', $expense, 'Expense updated');

        return response()->json($expense->load(['category', 'vendor', 'department', 'requester']));
    }

    /**
     * Draft -> Approved
     */
    public function approve(Expense $expense)
    {
        $this->ensureCan('approve_expenses');

        abort_if($expense->status !== 'draft', 422, 'Only draft expenses can be approved.');

        $expense->update(['status' => 'approved']);

        AuditService::log('expense_approved', $expense, 'Expense approved');

        return response()->json([
            'message' => 'Expense approved.',
            'expense' => $expense->fresh()->load(['category', 'vendor', 'department', 'requester']),
        ]);
    }

    /**
     * Approved -> Paid
     */
    public function markPaid(Expense $expense)
    {
        $this->ensureCan('pay_expenses');

        abort_if($expense->status !== 'approved', 422, 'Only approved expenses can be marked as paid.');

        $expense->update(['status' => 'paid']);

        AuditService::log('expense_paid', $expense, 'Expense marked as paid');

        return response()->json([
            'message' => 'Expense marked as paid.',
            'expense' => $expense->fresh()->load(['category', 'vendor', 'department', 'requester']),
        ]);
    }

    /**
     * Draft/Approved -> Cancelled. Paid expenses cannot be cancelled.
     */
    public function cancel(Expense $expense)
    {
        $this->ensureCan('cancel_expenses');

        abort_if($expense->status === 'paid', 422, 'Paid expenses cannot be cancelled.');

        $expense->update(['status' => 'cancelled']);

        AuditService::log('expense_cancelled', $expense, 'Expense cancelled');

        return response()->json([
            'message' => 'Expense cancelled.',
            'expense' => $expense->fresh()->load(['category', 'vendor', 'department', 'requester']),
        ]);
    }

    public function destroy(Expense $expense)
    {
        $this->ensureCan('edit_expenses');

        abort_if($expense->status === 'paid', 422, 'Paid expenses cannot be deleted.');

        $expense->delete();

        return response()->json(null, 204);
    }
}