<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = Customer::withCount('orders')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $customer = Customer::findOrCreateMatching($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->full_name} saved successfully.");
    }

    public function show(Customer $customer): View
    {
        $customer->load(['orders' => fn ($q) => $q->latest()]);

        return view('admin.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();

        $validated['phone_number'] = Customer::normalizePhoneNumber($validated['phone_number']);
        $customer->update($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->full_name} updated successfully.");
    }
}
