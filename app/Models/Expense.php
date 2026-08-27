<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_no',
        'expense_category_id',
        'vendor_id',
        'department_id',
        'requested_by',
        'amount',
        'currency',
        'expense_date',
        'payment_method',
        'reference',
        'invoice_number',
        'description',
        'status',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    
    public static function generateNumber(int $id): string
    {
        return 'EXP-'.now()->format('Ym').'-'.str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Assign the ID-derived expense number if one is not already set.
     */
    public static function assignExpenseNo(Expense $expense): Expense
    {
        if (empty($expense->expense_no)) {
            $expense->expense_no = static::generateNumber($expense->id);
            $expense->save();
        }

        return $expense->fresh();
    }
}