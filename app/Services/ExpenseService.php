<?php

namespace App\Services;

use App\Models\{Expense, ExpenseAudit, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class ExpenseService
{
    private function values(Expense $expense): array
    {
        return $expense->only(['user_id', 'description', 'category', 'amount', 'expense_date', 'edited_by', 'deleted_by', 'deletion_reason', 'deleted_at', 'version']);
    }

    private function audit(Expense $expense, User $actor, string $action, ?array $before, ?string $reason = null): void
    {
        ExpenseAudit::create(['expense_id' => $expense->id, 'actor_id' => $actor->id, 'action' => $action,
            'before_values' => $before, 'after_values' => $this->values($expense), 'reason' => $reason, 'recorded_at' => now()]);
    }

    public function create(array $data, User $actor): Expense
    {
        StaffAccess::require($actor);
        try {
            return DB::transaction(function () use ($data, $actor) {
                $existing = Expense::withTrashed()->where('submission_key', $data['submission_key'])->first();
                if ($existing) return $this->replay($existing, $data, $actor);
                $expense = Expense::create($data + ['user_id' => $actor->id]);
                $this->audit($expense, $actor, 'created', null);
                return $expense;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Expense::withTrashed()->where('submission_key', $data['submission_key'])->first();
            if (!$existing) throw $exception;
            return $this->replay($existing, $data, $actor);
        }
    }

    private function replay(Expense $expense, array $data, User $actor): Expense
    {
        $creation = $expense->audits()->where('action', 'created')->first()?->after_values;
        foreach (['description', 'category', 'amount', 'expense_date'] as $key) {
            $old = $creation[$key] ?? null;
            $same = $key === 'amount' ? round((float) $old * 100) === round((float) $data[$key] * 100)
                : ($key === 'expense_date' ? substr((string) $old, 0, 10) === $data[$key] : $old === $data[$key]);
            if (!$same || $expense->user_id !== $actor->id) throw ValidationException::withMessages(['submission_key' => 'This form was already saved. Open a new expense.']);
        }
        return $expense;
    }

    public function update(Expense $expense, array $data, User $actor): Expense
    {
        StaffAccess::require($actor);
        return DB::transaction(function () use ($expense, $data, $actor) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($expense, $data['version']);
            $before = $this->values($expense);
            $reason = $data['reason'] ?? null;
            unset($data['reason']);
            $expense->fill($data);
            $expense->edited_by = $actor->id;
            $expense->version++;
            $expense->save();
            $this->audit($expense, $actor, 'edited', $before, $reason);
            return $expense;
        }, 5);
    }

    public function void(Expense $expense, int $version, string $reason, User $actor): void
    {
        StaffAccess::require($actor);
        if (!trim($reason)) throw ValidationException::withMessages(['reason' => 'Enter a reason for deletion.']);
        DB::transaction(function () use ($expense, $version, $reason, $actor) {
            $expense = Expense::withTrashed()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->trashed()) return;
            $this->checkVersion($expense, $version);
            $before = $this->values($expense);
            $expense->deleted_by = $actor->id;
            $expense->deletion_reason = $reason;
            $expense->version++;
            $expense->deleted_at = now();
            $expense->save();
            $this->audit($expense, $actor, 'voided', $before, $reason);
        }, 5);
    }

    private function checkVersion(Expense $expense, int $version): void
    {
        if ((int) $expense->version !== $version) throw ValidationException::withMessages(['version' => 'This expense changed while the form was open. Review the latest record before saving again.']);
    }
}
