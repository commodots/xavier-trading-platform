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

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->expense_no)) {
                $expense->expense_no = static::generate();
            }
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public static function generate(): string
    {
        $next = self::query()->count() + 1;

        return 'EXP-' . date('Ym') . '-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
