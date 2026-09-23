<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::withCount('orderDetails')->orderBy('product_name')->get();

        return view('admin.products.index', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255', 'unique:products,product_name'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        Product::create($validated);

        return back()->with('success', "Product '{$validated['product_name']}' created successfully.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255', 'unique:products,product_name,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);
        $product->update($validated);

        return back()->with('success', "Product '{$product->product_name}' updated successfully.");
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->is_active = !$product->is_active;
        $product->save();

        $statusStr = $product->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Product '{$product->product_name}' has been {$statusStr}.");
    }
}
