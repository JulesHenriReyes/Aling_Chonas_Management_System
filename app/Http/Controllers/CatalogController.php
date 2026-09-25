<?php

namespace App\Http\Controllers;

use App\Models\AddOn;
use App\Models\PackageOption;
use App\Models\PaymentSetting;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    private const PHOTO = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    private const PRICE = ['required', 'numeric', 'decimal:0,2', 'min:0.02', 'max:999999.98', 'multiple_of:0.02'];

    public function index()
    {
        $products = Product::with('options')->withCount('orderDetails')->orderBy('product_name')->get();
        $addOns = AddOn::with('products')->orderBy('name')->get();

        return view('admin.catalog.index', compact('products', 'addOns'));
    }

    public function editProduct(?Product $product = null)
    {
        $product ??= new Product(['is_active' => true]);
        $product->load('options');

        return view('admin.catalog.product', compact('product'));
    }

    public function saveProduct(Request $request, ?Product $product = null)
    {
        $product ??= new Product();
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => self::PHOTO, 'is_active' => ['nullable', 'boolean'],
        ]);
        unset($data['photo']);
        $data['is_active'] = $request->boolean('is_active');
        // The legacy price remains for historical compatibility only. New
        // order prices always come from the explicit option record.
        if (!$product->exists) {
            $data['price'] = 0;
        }
        $this->savePhoto($request, $product, $data, 'catalog/packages');

        return redirect()->route('products.edit', $product)->with('success', 'Package saved. Configure its layer options and inclusions below.');
    }

    public function saveOption(Request $request, Product $product, ?PackageOption $option = null)
    {
        abort_if($option && $option->product_id !== $product->id, 404);
        $data = $request->validate([
            'layers' => ['required', 'integer', 'between:1,10', Rule::unique('package_options')->where('product_id', $product->id)->ignore($option?->id)],
            'included_contents' => ['required', 'string', 'max:2000'],
            'price' => self::PRICE, 'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $option ? $option->update($data) : $product->options()->create($data);

        return redirect()->route('products.edit', $product)->with('success', 'Layer option saved. Existing order snapshots are unchanged.');
    }

    public function toggleProduct(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);

        return back()->with('success', 'Package availability updated.');
    }

    public function editAddOn(?AddOn $addOn = null)
    {
        $addOn ??= new AddOn(['is_active' => true]);
        $products = Product::orderBy('product_name')->get();

        return view('admin.catalog.add-on', compact('addOn', 'products'));
    }

    public function saveAddOn(Request $request, ?AddOn $addOn = null)
    {
        $addOn ??= new AddOn();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => self::PRICE, 'photo' => self::PHOTO,
            'is_active' => ['nullable', 'boolean'],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
        ]);
        $packages = $data['products'];
        unset($data['photo'], $data['products']);
        $data['is_active'] = $request->boolean('is_active');
        $this->savePhoto($request, $addOn, $data, 'catalog/add-ons', fn () => $addOn->products()->sync($packages));

        return redirect()->route('products.index')->with('success', 'Add-on saved.');
    }

    public function settings()
    {
        $settings = PaymentSetting::first() ?? new PaymentSetting();

        return view('admin.catalog.payment-settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $settings = PaymentSetting::first() ?? new PaymentSetting(['id' => 1]);
        if (!$settings->exists) {
            $settings->id = 1;
        }
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:32'],
            'photo' => array_merge($settings->qr_path ? ['nullable'] : ['required'], ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120']),
        ]);
        unset($data['photo']);
        $data['updated_by'] = $request->user()->id;
        $this->savePhoto($request, $settings, $data, 'business/gcash', null, 'qr_path');

        return redirect()->route('payment-settings.edit')->with('success', 'Business GCash details saved.');
    }

    private function savePhoto(Request $request, $model, array $data, string $folder, ?callable $afterSave = null, string $column = 'photo_path'): void
    {
        $oldPath = $model->{$column};
        $newPath = null;
        try {
            if ($request->hasFile('photo')) {
                $newPath = $request->file('photo')->store($folder, 'public');
                if (!$newPath) {
                    throw new \RuntimeException('The catalog image could not be stored. Please try again.');
                }
                $data[$column] = $newPath;
            }
            DB::transaction(function () use ($model, $data, $afterSave) {
                $model->fill($data)->save();
                if ($afterSave) {
                    $afterSave();
                }
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
