<?php

namespace App\Http\Controllers;

use App\Models\{Expense, ExpenseAudit};
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['ingredients', 'packaging', 'equipment', 'miscellaneous'];
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('manage-expenses');
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:active,voided,all'], 'sort' => ['nullable', 'in:expense_date,description,category,amount'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $query = Expense::with('user');
        if ($request->status === 'voided') $query->onlyTrashed();
        elseif ($request->status === 'all') $query->withTrashed();
        if ($request->filled('q')) $query->where('description', 'like', '%'.$request->string('q').'%');
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('start_date')) $query->where('expense_date', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->where('expense_date', '<', \Carbon\Carbon::parse($request->end_date)->addDay()->toDateString());
        $totalExpenses = (float) (clone $query)->whereNull('deleted_at')->sum('amount');
        $expenses = $query->orderBy($request->input('sort', 'expense_date'), $request->input('direction', 'desc'))->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.expenses.index', compact('expenses', 'totalExpenses'));
    }

    public function create()
    {
        Gate::authorize('manage-expenses');
        return view('admin.expenses.form', ['expense' => new Expense]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-expenses');
        $data = $this->validateExpense($request);
        $data += $request->validate(['submission_key' => ['nullable', 'uuid']]);
        $data['submission_key'] ??= (string) Str::uuid();
        $expense = $this->service->create($data, $request->user());
        return redirect()->route('expenses.show', $expense)->with('success', 'Expense recorded.');
    }

    public function show(int $expense)
    {
        Gate::authorize('manage-expenses');
        $expense = Expense::withTrashed()->with(['user', 'editor'])->findOrFail($expense);
        return view('admin.expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        Gate::authorize('manage-expenses');
        return view('admin.expenses.form', compact('expense'));
    }

    public function update(Request $request, Expense $expense)
    {
        Gate::authorize('manage-expenses');
        $data = $this->validateExpense($request) + $request->validate(['version' => ['required', 'integer', 'min:0'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->update($expense, $data, $request->user());
        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, int $expense)
    {
        Gate::authorize('manage-expenses');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'version' => ['required', 'integer', 'min:0']]);
        $expense = Expense::withTrashed()->findOrFail($expense);
        $this->service->void($expense, (int) $data['version'], $data['reason'], $request->user());
        return redirect()->route('expenses.show', $expense)->with('success', 'Expense voided and excluded from active totals.');
    }

    public function history(Request $request)
    {
        Gate::authorize('manage-expenses');
        $request->validate(['expense_id' => ['nullable', 'integer'], 'action' => ['nullable', 'in:created,edited,voided,legacy_baseline']]);
        $query = ExpenseAudit::with(['actor', 'expense']);
        if ($request->filled('expense_id')) $query->where('expense_id', $request->expense_id);
        if ($request->filled('action')) $query->where('action', $request->action);
        return view('admin.expenses.history', ['audits' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'], 'category' => ['required', Rule::in(self::CATEGORIES)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
        ]);
    }
}
