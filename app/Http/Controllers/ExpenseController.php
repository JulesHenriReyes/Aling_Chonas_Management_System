<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Expense::with('user')->latest('expense_date');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $expenses = $query->paginate(20)->withQueryString();
        $totalExpenses = (float) (clone $query)->sum('amount');

        return view('admin.expenses.index', compact('expenses', 'totalExpenses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['ingredients', 'packaging', 'equipment', 'miscellaneous'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
        ]);

        $validated['user_id'] = Auth::id();

        Expense::create($validated);

        return back()->with('success', "Expense of ₱" . number_format($validated['amount'], 2) . " recorded.");
    }
}
