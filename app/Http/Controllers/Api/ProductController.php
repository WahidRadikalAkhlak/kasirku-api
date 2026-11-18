<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $query = Product::where('store_id', $store->id);

        if ($request->filled('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'business_type' => 'required|in:retail,coffeeShop,restaurant',
            'category'      => 'required|string|max:100',
            'barcode'       => 'nullable|string|max:100',
            'name'          => 'required|string|max:255',
            'price'         => 'required|numeric|min:0',
            'stock'         => 'nullable|integer|min:0',
            'variants'      => 'nullable',
            'image_url'     => 'nullable|url',
            'image_file'    => 'nullable|image|max:2048',
        ]);

        if (isset($validated['variants']) && is_string($validated['variants'])) {
            $validated['variants'] = json_decode($validated['variants'], true);
        }

        if ($request->hasFile('image_file')) {
        $path = $request->file('image_file')->store('products', 'public');
        $validated['image_url'] = url('/api/files/' . $path);
        }

        $validated['store_id'] = $store->id;
        $validated['stock'] = $validated['stock'] ?? 0;

        $product = Product::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil disimpan',
            'data'    => $product,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        $product = Product::where('store_id', $store->id)->findOrFail($id);
        $product->delete();

        return response()->json(['status' => 'success', 'message' => 'Produk dihapus']);
    }

// Tambahkan method ini di dalam class ProductController
    public function update(Request $request, $id)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        $product = Product::where('store_id', $store->id)->findOrFail($id);

        $validated = $request->validate([
            'category'   => 'required|string|max:100',
            'barcode'    => 'nullable|string|max:100',
            'name'       => 'required|string|max:255',
            'price'      => 'required|numeric|min:0',
            'stock'      => 'nullable|integer|min:0',
            'variants'   => 'nullable',
            'image_url'  => 'nullable|url',
            'image_file' => 'nullable|image|max:2048',
        ]);

        if (isset($validated['variants']) && is_string($validated['variants'])) {
            $validated['variants'] = json_decode($validated['variants'], true);
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('products', 'public');
            $validated['image_url'] = url('/api/files/' . $path);
        }

        if ($store->business_type === 'retail') {
            $validated['stock'] = $validated['stock'] ?? $product->stock;
        }

        $product->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data'    => $product,
        ]);
    }
}
