<?php

namespace App\Services;

use App\Models\{InventoryTransaction, StockAllocation, StockEntry, Supply, User};
use App\Support\InventoryCalendar;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class StockEntryLedger
{
    public static function units(mixed $quantity): int { return (int)round((float)$quantity * 100); }

    public function opening(Supply $supply): void
    {
        if (self::units($supply->current_quantity) > 0 && !$supply->stockEntries()->exists()) {
            StockEntry::create(['supply_id' => $supply->id, 'source' => 'opening_stock', 'opening_quantity' => $supply->current_quantity, 'remaining_quantity' => $supply->current_quantity]);
        }
    }

    public function reconcile(Supply $supply): void
    {
        if (self::units($supply->stockEntries()->sum('remaining_quantity')) !== self::units($supply->current_quantity)) {
            $this->fail('notes', 'Stock entries do not reconcile with on-hand stock. No changes were saved; ask the Owner to review inventory.');
        }
    }

    public function ordered(Supply $supply, Collection $entries, bool $usable = true): Collection
    {
        return $entries->filter(fn ($entry) => self::units($entry->remaining_quantity) > 0 && (!$usable || $supply->category === 'packaging' || ($entry->expiry_date && $entry->expiry_date->toDateString() >= InventoryCalendar::date())))
            ->sortBy(fn ($entry) => ($supply->category === 'ingredients' ? ($entry->expiry_date?->toDateString() ?? '9999-12-31') : '') . '|'.($entry->stock_in_date?->toDateString() ?? '0000-00-00').'|'.str_pad((string)$entry->id, 20, '0', STR_PAD_LEFT))->values();
    }

    public function preview(array $lines): array
    {
        $results = [];
        foreach ($lines as $index => $line) {
            if (!empty($line['stock_entry_id']) || !empty($line['entries']) || !empty($line['allocations'])) $this->fail("lines.$index.quantity", 'The system selects stock entries automatically for baking.');
            $supply = Supply::active()->with('stockEntries')->find($line['supply_id']);
            if (!$supply) $this->fail("lines.$index.supply_id", 'Choose an active supply.');
            $candidates = $this->ordered($supply, $supply->stockEntries);
            $changes = $this->take($candidates, self::units($line['quantity']), "lines.$index.quantity", $supply);
            $results[] = ['supply_id' => $supply->id, 'expected_version' => (int)$supply->stock_version,
                'available_quantity' => $candidates->sum('remaining_quantity'), 'on_hand_quantity' => (float)$supply->current_quantity,
                'allocations' => array_map(fn ($change) => ['stock_entry_id' => $change['entry']->id, 'expiry_date' => $change['entry']->expiry_date?->toDateString(), 'quantity' => -$change['delta'] / 100], $changes)];
        }
        return ['business_date' => InventoryCalendar::date(), 'lines' => $results];
    }

    /** Called with the parent supply and all its entries locked by InventoryService. */
    public function plan(Supply $supply, array $line, string $type, int $delta, string $date, int $index, ?InventoryTransaction $original, User $actor): array
    {
        $this->reconcile($supply);
        $entries = $supply->stockEntries()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $field = "lines.$index.quantity";
        if ($type === 'reversal') {
            $allocations = $original->allocations()->with('entry')->get();
            if ($allocations->isNotEmpty()) return $allocations->map(fn ($allocation) => ['entry' => $entries->get($allocation->stock_entry_id), 'delta' => -self::units($allocation->quantity), 'reversal_of_id' => $allocation->id])->all();
            if ($supply->category === 'ingredients') {
                StaffAccess::requireOwner($actor);
                if ($delta < 0) return $this->selected($entries, $line['reconciliation'] ?? [], -$delta, $field);
                return $delta === 0 ? [] : [['entry' => $this->newEntry($supply, 'legacy_reconciliation', null, null, null, $delta), 'delta' => $delta]];
            }
            return $delta < 0 ? $this->take($this->ordered($supply, $entries, false), -$delta, $field, $supply) : ($delta === 0 ? [] : [['entry' => $this->newEntry($supply, 'legacy_reconciliation', null, null, null, $delta), 'delta' => $delta]]);
        }
        if ($type === 'expiry_verification') {
            StaffAccess::requireOwner($actor);
            $entry = $entries->get($line['stock_entry_id'] ?? 0);
            if (!$entry || $supply->category !== 'ingredients' || $entry->expiry_date || !in_array($entry->source, ['opening_stock', 'legacy_reconciliation']) || self::units($entry->remaining_quantity) <= 0) $this->fail($field, 'Choose an existing unknown-expiry opening entry.');
            $splits = $line['splits'] ?? [];
            if (!$splits || array_sum(array_map(fn ($part) => self::units($part['quantity']), $splits)) !== self::units($entry->remaining_quantity)) $this->fail($field, 'Verified quantities must equal the complete remaining opening quantity.');
            $changes = [['entry' => $entry, 'delta' => -self::units($entry->remaining_quantity)]];
            foreach ($splits as $part) {
                if (empty($part['expiry_date']) || self::units($part['quantity']) <= 0) $this->fail($field, 'Each verified portion needs a positive quantity and a verified expiry date.');
                $changes[] = ['entry' => $this->newEntry($supply, 'verified_opening', $entry->stock_in_date?->toDateString(), $part['expiry_date'], $entry->id, self::units($part['quantity'])), 'delta' => self::units($part['quantity'])];
            }
            return $changes;
        }
        if ($type === 'receipt' || ($type === 'adjustment' && $delta > 0)) {
            $expiry = $supply->category === 'ingredients' ? ($line['expiry_date'] ?? null) : null;
            if ($supply->category === 'ingredients' && !$expiry) $this->fail("lines.$index.expiry_date", 'Enter the expiration date for this ingredient.');
            if ($expiry && $expiry < $date) $this->fail("lines.$index.expiry_date", 'Expiration cannot be before the stock-in date.');
            return [['entry' => $this->newEntry($supply, $type === 'receipt' ? 'stock_in' : 'adjustment', $date, $expiry, null, $delta), 'delta' => $delta]];
        }
        if ($type === 'usage') {
            if (!empty($line['stock_entry_id']) || !empty($line['entries']) || !empty($line['allocations'])) $this->fail($field, 'The system selects stock entries automatically for baking.');
            return $this->take($this->ordered($supply, $entries), -$delta, $field, $supply);
        }
        if ($type === 'waste' || ($type === 'adjustment' && $delta < 0 && $supply->category === 'ingredients')) {
            return $this->selected($entries, $line['entries'] ?? [], $type === 'adjustment' ? -$delta : null, $field);
        }
        if ($type === 'stocktake' && $supply->category === 'ingredients') {
            $counts = $line['entries'] ?? [];
            $required = $entries->filter(fn ($e) => self::units($e->remaining_quantity) > 0)->keys()->sort()->values()->all();
            $ids = array_map(fn ($row) => (int)$row['stock_entry_id'], $counts); sort($ids);
            if (!$required && !$counts) {
                if (self::units($line['quantity'] ?? 0) !== 0) $this->fail($field, 'Record new ingredient stock through dated Stock in.');
                return [];
            }
            if ($ids !== $required) $this->fail($field, 'Count every remaining entry of this ingredient, including expired and unknown stock.');
            return array_map(fn ($count) => ['entry' => $entries->get($count['stock_entry_id']), 'delta' => self::units($count['quantity']) - self::units($entries->get($count['stock_entry_id'])->remaining_quantity)], $counts);
        }
        // Packaging count/adjustment keeps its aggregate workflow.
        if ($delta < 0) return $this->take($this->ordered($supply, $entries, false), -$delta, $field, $supply);
        return $delta === 0 ? [] : [['entry' => $this->newEntry($supply, 'count_adjustment', null, null, null, $delta), 'delta' => $delta]];
    }

    private function selected(Collection $entries, array $rows, ?int $expected, string $field): array
    {
        $changes = []; $seen = [];
        foreach ($rows as $row) {
            $quantity = self::units($row['quantity']);
            if ($quantity === 0) continue;
            $entry = $entries->get($row['stock_entry_id']);
            if (!$entry || isset($seen[$entry->id]) || $quantity < 0 || $quantity > self::units($entry->remaining_quantity)) $this->fail($field, 'Select distinct stock entries and quantities within their remaining stock.');
            $seen[$entry->id] = true; $changes[] = ['entry' => $entry, 'delta' => -$quantity];
        }
        if (!$changes || ($expected !== null && -array_sum(array_column($changes, 'delta')) !== $expected)) $this->fail($field, 'Select the stock entries and exact quantity to remove.');
        return $changes;
    }

    private function take(Collection $entries, int $quantity, string $field, Supply $supply): array
    {
        $available = $entries->sum(fn ($entry) => self::units($entry->remaining_quantity));
        if ($quantity <= 0 || $quantity > $available) $this->fail($field, 'Usable stock: '.number_format($available / 100, 2).' '.$supply->unit.'. Expired and unknown ingredient stock cannot be used for baking.');
        $changes = [];
        foreach ($entries as $entry) {
            $take = min($quantity, self::units($entry->remaining_quantity));
            $changes[] = ['entry' => $entry, 'delta' => -$take]; $quantity -= $take;
            if (!$quantity) break;
        }
        return $changes;
    }

    private function newEntry(Supply $supply, string $source, ?string $date, ?string $expiry, ?int $parent = null, int $opening = 0): StockEntry
    {
        return StockEntry::create(['supply_id' => $supply->id, 'source' => $source, 'stock_in_date' => $date, 'expiry_date' => $expiry, 'opening_quantity' => $opening / 100, 'remaining_quantity' => 0, 'parent_entry_id' => $parent]);
    }

    public function apply(InventoryTransaction $movement, array $changes): void
    {
        foreach ($changes as $change) {
            if ($change['delta'] === 0) continue;
            $entry = $change['entry'];
            if (!$entry) $this->fail('notes', 'The original stock entry is unavailable.');
            $before = self::units($entry->remaining_quantity); $after = $before + $change['delta'];
            if ($after < 0 || $after > 9999999999) $this->fail('notes', 'The original entry no longer has enough stock to reverse this operation. No changes were saved.');
            StockAllocation::create(['inventory_transaction_id' => $movement->id, 'stock_entry_id' => $entry->id, 'quantity' => $change['delta'] / 100, 'quantity_before' => $before / 100, 'quantity_after' => $after / 100, 'reversal_of_id' => $change['reversal_of_id'] ?? null]);
            $entry->remaining_quantity = $after / 100; $entry->save();
        }
    }

    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }
}
