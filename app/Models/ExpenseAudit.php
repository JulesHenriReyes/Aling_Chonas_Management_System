<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseAudit extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'recorded_at' => 'datetime'];
    }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Expense history is read-only.'));
        static::deleting(fn () => throw new \LogicException('Expense history is read-only.'));
    }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function expense() { return $this->belongsTo(Expense::class)->withTrashed(); }
}
