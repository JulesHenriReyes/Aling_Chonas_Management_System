<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Supply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Record an inventory transaction and atomically synchronize supply current_quantity.
     */
    public function recordTransaction(
        Supply $supply,
        string $type,
        float $quantity,
        User $user,
        ?string $notes = null,
        ?Carbon $date = null
    ): InventoryTransaction {
        return DB::transaction(function () use ($supply, $type, $quantity, $user, $notes, $date) {
            // Lock the supply row for update to prevent race conditions
            $supply = Supply::where('id', $supply->id)->lockForUpdate()->firstOrFail();

            $currentStock = (float) $supply->current_quantity;
            $newStock = $currentStock;

            if ($type === 'stock_in') {
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Stock in quantity must be greater than zero.'],
                    ]);
                }
                $newStock = $currentStock + $quantity;
            } elseif ($type === 'stock_out') {
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Stock out quantity must be greater than zero.'],
                    ]);
                }
                if ($quantity > $currentStock) {
                    throw ValidationException::withMessages([
                        'quantity' => [
                            "Cannot stock out {$quantity} {$supply->unit}. Only {$currentStock} {$supply->unit} available. Negative stock is not allowed."
                        ],
                    ]);
                }
                $newStock = $currentStock - $quantity;
            } elseif ($type === 'adjustment') {
                if ($quantity == 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Adjustment quantity cannot be zero.'],
                    ]);
                }
                $newStock = $currentStock + $quantity;
                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => [
                            "Adjustment would result in negative stock ({$newStock} {$supply->unit}). Negative stock is not allowed."
                        ],
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'transaction_type' => ['Invalid transaction type.'],
                ]);
            }

            // 1. Create inventory transaction record
            $transaction = InventoryTransaction::create([
                'supply_id' => $supply->id,
                'user_id' => $user->id,
                'transaction_type' => $type,
                'quantity' => $quantity,
                'transaction_date' => $date ?? now(),
                'notes' => $notes,
            ]);

            // 2. Synchronize current_quantity
            $supply->current_quantity = round($newStock, 2);
            $supply->save();

            return $transaction;
        });
    }
}
