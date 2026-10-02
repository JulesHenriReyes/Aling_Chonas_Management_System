<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryOperation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['operation_date' => 'date']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Post a linked reversal to correct an operation.'));
        static::deleting(fn () => throw new \LogicException('Stock operation history cannot be deleted.'));
    }
    public function user() { return $this->belongsTo(User::class); }
    public function movements() { return $this->hasMany(InventoryTransaction::class); }
    public function reversal() { return $this->hasOne(self::class, 'reversal_of_id'); }
    public function original() { return $this->belongsTo(self::class, 'reversal_of_id'); }
}
