<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'description',
        'category',
        'amount',
        'expense_date',
        'edited_by', 'deleted_by', 'deletion_reason', 'version', 'submission_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'edited_by'); }
    public function audits() { return $this->hasMany(ExpenseAudit::class); }
}
