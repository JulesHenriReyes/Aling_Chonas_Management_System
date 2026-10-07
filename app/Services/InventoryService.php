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
    public function __construct(private ?StockEntryLedger $entries = null) { $this->entries ??= new StockEntryLedger; }

    public function establishBaseline(Supply $supply, string $source = 'opening_balance'): void
    {
        $this->entries->opening($supply);
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
            'type' => ['required', 'in:receipt,usage,waste,stocktake,adjustment,reversal,expiry_verification'],
            'operation_date' => ['required', 'date_format:Y-m-d'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['required_if:type,waste,stocktake,adjustment,reversal,expiry_verification', 'nullable', 'string', 'max:2000'],
            'business_date' => ['required_if:type,usage', 'nullable', 'date_format:Y-m-d'],
            'reversal_of_id' => ['nullable', 'integer'],
            'legacy_reversal_of_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.supply_id' => ['required', 'integer', ...(($data['type'] ?? '') === 'reversal' || ($data['type'] ?? '') === 'expiry_verification' ? [] : ['distinct']), 'exists:supplies,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'decimal:0,2', 'between:-99999999.99,99999999.99'],
            'lines.*.expected_version' => ['required_if:type,usage,stocktake,expiry_verification', 'nullable', 'integer', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'lines.*.stock_entry_id' => ['nullable', 'integer'],
            'lines.*.movement_id' => ['nullable', 'integer'],
            'lines.*.allocations' => ['nullable', 'array'],
            'lines.*.entries' => ['nullable', 'array', 'max:100'],
            'lines.*.entries.*.stock_entry_id' => ['required', 'integer'],
            'lines.*.entries.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
            'lines.*.splits' => ['nullable', 'array', 'max:100'],
            'lines.*.splits.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'lines.*.splits.*.expiry_date' => ['required', 'date_format:Y-m-d'],
            'lines.*.reconciliation' => ['nullable', 'array', 'max:100'],
            'lines.*.reconciliation.*.stock_entry_id' => ['required', 'integer'],
            'lines.*.reconciliation.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
        ])->validate();
        if ($data['type'] === 'expiry_verification') StaffAccess::requireOwner($user);
        // Preview dates/versions describe a snapshot, rather than the user's movement.
        // Refreshing that snapshot must not turn a duplicate submit into another post.
        $intent = $data;
        unset($intent['business_date']);
        foreach ($intent['lines'] as &$intentLine) unset($intentLine['expected_version']);
        unset($intentLine);
        $hash = hash('sha256', json_encode($intent));
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
                if ($replayed = InventoryOperation::where('submission_key', $data['submission_key'])->first()) return $this->replay($replayed, $hash);
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
                        if ($original->type === 'expiry_verification') StaffAccess::requireOwner($user);
                    }
                }
                // Stable lock order prevents multi-item batches deadlocking each other.
                $supplies = Supply::whereIn('id', array_column($data['lines'], 'supply_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $startingVersions = $supplies->map(fn ($supply) => (int)$supply->stock_version);
                $operation = InventoryOperation::create([
                    'submission_key' => $data['submission_key'], 'payload_hash' => $hash,
                    'type' => $data['type'], 'operation_date' => $data['operation_date'], 'user_id' => $user->id,
                    'supplier' => $data['supplier'] ?? null, 'delivery_reference' => $data['delivery_reference'] ?? null,
                    'notes' => $data['notes'] ?? null, 'reversal_of_id' => $original?->id,
                ]);
                $originalLines = $legacy ? collect([$legacy])->keyBy('id') : $original?->movements()->get()->keyBy('id');
                if ($originalLines && $originalLines->count() !== count($data['lines'])) {
                    throw ValidationException::withMessages(['lines' => 'Reverse all lines in the original operation together.']);
                }
                if ($originalLines && array_diff($originalLines->keys()->all(), array_column($data['lines'], 'movement_id'))) throw ValidationException::withMessages(['lines' => 'Reverse each original movement exactly once.']);
                foreach ($data['lines'] as $index => $line) {
                    $supply = $supplies->get($line['supply_id']);
                    if (!$supply || (!$supply->is_active && !$original && !$legacy)) {
                        throw ValidationException::withMessages(["lines.$index.supply_id" => 'Choose an active supply.']);
                    }
                    $this->establishBaseline($supply, 'legacy_reconciliation');
                    $before = (int) round((float) $supply->current_quantity * 100);
                    $quantity = (int) round((float) ($line['quantity'] ?? 0) * 100);
                    $type = $data['type'];
                    if ($type === 'waste' && !isset($line['quantity'])) $quantity = array_sum(array_map(fn ($row) => StockEntryLedger::units($row['quantity']), $line['entries'] ?? []));
                    if (in_array($type, ['receipt', 'usage', 'waste']) && $quantity <= 0) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => 'Enter a quantity greater than zero.']);
                    }
                    if (in_array($type, ['usage','stocktake','expiry_verification']) && ($quantity < 0 || (int) $line['expected_version'] !== $startingVersions->get($supply->id) || ($type === 'usage' && $data['business_date'] !== \App\Support\InventoryCalendar::date()))) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => $quantity < 0 ? 'Count cannot be negative.' : ($type === 'usage' ? 'Stock or the business date changed. Refresh the allocation preview; your entered quantities are preserved.' : 'Stock changed while this review was open. Reload current stock and recount or verify this supply.')]);
                    }
                    $delta = match ($type) {
                        'receipt' => $quantity, 'usage', 'waste' => -$quantity,
                        'stocktake' => $quantity - $before, default => $quantity,
                    };
                    $reversed = $originalLines?->get($line['movement_id'] ?? 0);
                    if ($original || $legacy) {
                        if (!$reversed || (int)$reversed->supply_id !== (int)$supply->id) throw ValidationException::withMessages(['lines' => 'Original movement is missing or belongs to another supply.']);
                        $delta = (int) round((float) $reversed->quantity * ($reversed->transaction_type === 'stock_out' ? 100 : -100));
                    }
                    $changes = $this->entries->plan($supply, $line, $type, $delta, $data['operation_date'], $index, $reversed, $user);
                    $delta = array_sum(array_column($changes, 'delta'));
                    $after = $before + $delta;
                    if ($after < 0 || $after > 9999999999) {
                        throw ValidationException::withMessages(["lines.$index.quantity" => "Available: {$supply->current_quantity} {$supply->unit}. This change would exceed the allowed stock range."]);
                    }
                    $movementType = match ($type) { 'receipt' => 'stock_in', 'usage', 'waste' => 'stock_out', default => 'adjustment' };
                    $movement = InventoryTransaction::create([
                        'inventory_operation_id' => $operation->id, 'supply_id' => $supply->id, 'user_id' => $user->id,
                        'transaction_type' => $movementType, 'quantity' => ($movementType === 'stock_out' ? -$delta : $delta) / 100,
                        'quantity_before' => $before / 100, 'quantity_after' => $after / 100, 'unit' => $supply->unit,
                        'transaction_date' => $data['operation_date'].' 00:00:00', 'notes' => $data['notes'] ?? null,
                        'reversal_of_id' => $reversed?->id,
                    ]);
                    $this->entries->apply($movement, $changes);
                    $supply->current_quantity = $after / 100;
                    $supply->stock_version = (int) $supply->stock_version + 1;
                    $supply->save();
                    $this->entries->reconcile($supply);
                }
                if ($data['type'] === 'usage' && $data['business_date'] !== \App\Support\InventoryCalendar::date()) throw ValidationException::withMessages(['business_date' => 'The business date changed. Refresh the allocation preview.']);
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

    public function reverse(InventoryOperation $original, string $key, string $reason, User $user, array $reconciliation = []): InventoryOperation
    {
        return $this->post([
            'submission_key' => $key, 'type' => 'reversal', 'operation_date' => \App\Support\InventoryCalendar::date(),
            'notes' => $reason, 'reversal_of_id' => $original->id,
            'lines' => $original->movements()->get()->map(fn ($line) => ['movement_id' => $line->id, 'supply_id' => $line->supply_id, 'quantity' => 0, 'reconciliation' => $reconciliation[$line->id] ?? []])->all(),
        ], $user);
    }

    public function reverseLegacy(InventoryTransaction $original, string $key, string $reason, User $user, array $reconciliation = []): InventoryOperation
    {
        return $this->post([
            'submission_key' => $key, 'type' => 'reversal', 'operation_date' => \App\Support\InventoryCalendar::date(),
            'notes' => $reason, 'legacy_reversal_of_id' => $original->id,
            'lines' => [['movement_id' => $original->id, 'supply_id' => $original->supply_id, 'quantity' => 0, 'reconciliation' => $reconciliation[$original->id] ?? []]],
        ], $user);
    }

    public function recordTransaction(Supply $supply, string $type, float $quantity, User $user, ?string $notes = null, ?Carbon $date = null, ?string $key = null, array $entryData = []): InventoryTransaction
    {
        $type = match ($type) { 'stock_in' => 'receipt', 'stock_out' => 'usage', 'adjustment' => 'adjustment', default => '' };
        return $this->post([
            'submission_key' => $key ?? (string) Str::uuid(), 'type' => $type, 'operation_date' => ($date ?? now())->toDateString(),
            'notes' => $notes, 'lines' => [array_merge(['supply_id' => $supply->id, 'quantity' => $quantity, 'expected_version' => (int)$supply->fresh()->stock_version], $entryData)],
            'business_date' => \App\Support\InventoryCalendar::date(),
        ], $user)->movements()->firstOrFail();
    }
}
