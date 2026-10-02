<?php

namespace App\Services;

use App\Models\{InventoryOperation, InventoryTransaction, Supply, User};
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function establishBaseline(Supply $supply, string $source = 'opening_balance'): void
    {
        if (DB::table('inventory_baselines')->where('supply_id', $supply->id)->exists()) return;
        $net = $supply->inventoryTransactions()->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'stock_out' THEN -quantity ELSE quantity END), 0) AS net")->value('net');
        DB::table('inventory_baselines')->insert([
            'supply_id' => $supply->id, 'opening_quantity' => round($supply->current_quantity - $net, 2),
            'observed_quantity' => $supply->current_quantity, 'legacy_net_quantity' => $net,
            'unit' => $supply->unit, 'source' => $source, 'established_at' => now(),
        ]);
    }

    public function post(array $data, User $user): InventoryOperation
    {
        StaffAccess::require($user);
        $data = Validator::make($data, [
            'submission_key' => ['required', 'uuid'],
            'type' => ['required', 'in:receipt,usage,waste,stocktake,adjustment,reversal'],
            'operation_date' => ['required', 'date_format:Y-m-d'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['required_if:type,waste,stocktake,adjustment,reversal', 'nullable', 'string', 'max:2000'],
            'reversal_of_id' => ['nullable', 'integer'],
            'legacy_reversal_of_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.supply_id' => ['required', 'integer', 'distinct', 'exists:supplies,id'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'between:-99999999.99,99999999.99'],
            'lines.*.expected_version' => ['required_if:type,stocktake', 'nullable', 'integer', 'min:0'],
        ])->validate();
        $hash = hash('sha256', json_encode($data));
        $existing = InventoryOperation::where('submission_key', $data['submission_key'])->first();
        if ($existing) return $this->replay($existing, $hash);
        try {
            return DB::transaction(function () use ($data, $user, $hash) {
                // SQLite has no row locks; PHP <8.4 starts a deferred transaction even
                // with IMMEDIATE configured. Acquire its writer lock before any read.
                if (DB::connection()->getDriverName() === 'sqlite') {
                    DB::table('supplies')->whereIn('id', array_column($data['lines'], 'supply_id'))
                        ->update(['stock_version' => DB::raw('stock_version')]);
                }
                $original = null;
                $legacy = null;
                if ($data['type'] === 'reversal') {
                    if (!empty($data['legacy_reversal_of_id'])) {
                        $legacy = InventoryTransaction::whereKey($data['legacy_reversal_of_id'])->lockForUpdate()->firstOrFail();
                        if ($legacy->inventory_operation_id || $legacy->reversal_of_id || $legacy->reversal()->exists()) {
                            throw ValidationException::withMessages(['notes' => 'This movement is grouped or already reversed. Open its operation history.']);
                        }
                    } else {
                        $original = InventoryOperation::whereKey($data['reversal_of_id'] ?? 0)->lockForUpdate()->firstOrFail();
                        if ($original->type === 'reversal' || $original->reversal()->exists()) {
                            throw ValidationException::withMessages(['notes' => 'This operation has already been reversed or is itself a reversal.']);
                        }
                    }
                }
                // Stable lock order prevents multi-item batches deadlocking each other.
                $supplies = Supply::whereIn('id', array_column($data['lines'], 'supply_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $operation = InventoryOperation::create([
                    'submission_key' => $data['submission_key'], 'payload_hash' => $hash,
                    'type' => $data['type'], 'operation_date' => $data['operation_date'], 'user_id' => $user->id,
                    'supplier' => $data['supplier'] ?? null, 'delivery_reference' => $data['delivery_reference'] ?? null,
                    'notes' => $data['notes'] ?? null, 'reversal_of_id' => $original?->id,
                ]);
                $originalLines = $legacy ? collect([$legacy])->keyBy('supply_id') : $original?->movements()->get()->keyBy('supply_id');
                if ($originalLines && $originalLines->count() !== count($data['lines'])) {
                    throw ValidationException::withMessages(['lines' => 'Reverse all lines in the original operation together.']);
                }
                foreach ($data['lines'] as $index => $line) {
                    $supply = $supplies->get($line['supply_id']);
                    if (!$supply || (!$supply->is_active && !$original && !$legacy)) {
                        throw ValidationException::withMessages(["lines.$index.supply_id" => 'Choose an active supply.']);
                    }
                    $this->establishBaseline($supply, 'legacy_reconciliation');
                    $before = (int) round((float) $supply->current_quantity * 100);
                    $quantity = (int) round((float) $line['quantity'] * 100);
                    $type = $data['type'];
                    if (in_array($type, ['receipt', 'usage', 'waste']) && $quantity <= 0) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => 'Enter a quantity greater than zero.']);
                    }
                    if ($type === 'stocktake' && ($quantity < 0 || (int) $line['expected_version'] !== (int) $supply->stock_version)) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => $quantity < 0 ? 'Count cannot be negative.' : 'Stock changed while this count was open. Reload current stock and recount this supply.']);
                    }
                    $delta = match ($type) {
                        'receipt' => $quantity, 'usage', 'waste' => -$quantity,
                        'stocktake' => $quantity - $before, default => $quantity,
                    };
                    $reversed = $originalLines?->get($supply->id);
                    if ($original || $legacy) {
                        if (!$reversed) throw ValidationException::withMessages(['lines' => 'Original movement is missing.']);
                        $delta = (int) round((float) $reversed->quantity * ($reversed->transaction_type === 'stock_out' ? 100 : -100));
                    }
                    $after = $before + $delta;
                    if ($after < 0 || $after > 9999999999) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => "Available: {$supply->current_quantity} {$supply->unit}. This change would exceed the allowed stock range."]);
                    }
                    $movementType = match ($type) { 'receipt' => 'stock_in', 'usage', 'waste' => 'stock_out', default => 'adjustment' };
                    InventoryTransaction::create([
                        'inventory_operation_id' => $operation->id, 'supply_id' => $supply->id, 'user_id' => $user->id,
                        'transaction_type' => $movementType, 'quantity' => ($movementType === 'stock_out' ? -$delta : $delta) / 100,
                        'quantity_before' => $before / 100, 'quantity_after' => $after / 100, 'unit' => $supply->unit,
                        'transaction_date' => $data['operation_date'].' 00:00:00', 'notes' => $data['notes'] ?? null,
                        'reversal_of_id' => $reversed?->id,
                    ]);
                    $supply->current_quantity = $after / 100;
                    $supply->stock_version = (int) $supply->stock_version + 1;
                    $supply->save();
                }
                return $operation;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = InventoryOperation::where('submission_key', $data['submission_key'])->first();
            if ($existing) return $this->replay($existing, $hash);
            throw ValidationException::withMessages(['notes' => 'This operation has already been corrected. Refresh its history.']);
        }
    }

    private function replay(InventoryOperation $operation, string $hash): InventoryOperation
    {
        if (!hash_equals($operation->payload_hash, $hash)) {
            throw ValidationException::withMessages(['submission_key' => 'This form has already been posted with different values. Open a new operation.']);
        }
        return $operation;
    }

    public function reverse(InventoryOperation $original, string $key, string $reason, User $user): InventoryOperation
    {
        return $this->post([
            'submission_key' => $key, 'type' => 'reversal', 'operation_date' => now()->toDateString(),
            'notes' => $reason, 'reversal_of_id' => $original->id,
            'lines' => $original->movements()->get()->map(fn ($line) => ['supply_id' => $line->supply_id, 'quantity' => 0])->all(),
        ], $user);
    }

    public function reverseLegacy(InventoryTransaction $original, string $key, string $reason, User $user): InventoryOperation
    {
        return $this->post([
            'submission_key' => $key, 'type' => 'reversal', 'operation_date' => now()->toDateString(),
            'notes' => $reason, 'legacy_reversal_of_id' => $original->id,
            'lines' => [['supply_id' => $original->supply_id, 'quantity' => 0]],
        ], $user);
    }

    public function recordTransaction(Supply $supply, string $type, float $quantity, User $user, ?string $notes = null, ?Carbon $date = null, ?string $key = null): InventoryTransaction
    {
        $type = match ($type) { 'stock_in' => 'receipt', 'stock_out' => 'usage', 'adjustment' => 'adjustment', default => '' };
        return $this->post([
            'submission_key' => $key ?? (string) Str::uuid(), 'type' => $type, 'operation_date' => ($date ?? now())->toDateString(),
            'notes' => $notes, 'lines' => [['supply_id' => $supply->id, 'quantity' => $quantity]],
        ], $user)->movements()->firstOrFail();
    }
}
