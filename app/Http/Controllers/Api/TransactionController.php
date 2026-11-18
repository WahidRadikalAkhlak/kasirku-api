<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // <-- WAJIB DITAMBAHKAN
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    // Riwayat transaksi per toko/akun (hanya yang sudah lunas atau semua sesuai kebutuhan)
    public function index(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $transactions = Transaction::with('items')
            ->where('store_id', $store->id)
            ->where('payment_status', 'paid') // Hanya tampilkan yang sudah lunas
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $transactions,
        ]);
    }

    // Proses & simpan transaksi pembayaran (Cash, Transfer, QRIS)
    public function store(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'table_number'   => 'nullable|string|max:50',
            'subtotal'       => 'required|numeric|min:0',
            'tax_amount'     => 'required|numeric|min:0',
            'service_amount' => 'required|numeric|min:0',
            'grand_total'    => 'required|numeric|min:0',
            'paid_amount'    => 'required|numeric|min:0',
            'change_amount'  => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris',
            'items'          => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.product_name'     => 'required|string',
            'items.*.selected_variant' => 'nullable|string',
            'items.*.note'             => 'nullable|string',
            'items.*.qty'              => 'required|integer|min:1',
            'items.*.price'            => 'required|numeric|min:0',
            'items.*.total_price'      => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($store, $validated) {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $isCash = $validated['payment_method'] === 'cash';

            $qrisPayload = null;
            $paymentStatus = $isCash ? 'paid' : 'pending';

            // Jika QRIS atau Transfer, panggil Midtrans Core API
            if (!$isCash) {
                $serverKey = config('services.midtrans.server_key');
                if (empty($serverKey)) {
                    throw new \Exception('MIDTRANS_SERVER_KEY belum diisi di file .env Laravel.');
                }

                $baseUrl = config('services.midtrans.is_production')
                    ? 'https://api.midtrans.com/v2/charge'
                    : 'https://api.sandbox.midtrans.com/v2/charge';

                $midtransBody = [
                    'transaction_details' => [
                        'order_id'     => $invoiceNumber,
                        'gross_amount' => (int) round($validated['grand_total']),
                    ],
                ];

                if ($validated['payment_method'] === 'qris') {
                    $midtransBody['payment_type'] = 'qris';
                    $midtransBody['qris'] = ['acquirer' => 'gopay'];
                } else {
                    // Virtual Account BCA untuk metode Transfer
                    $midtransBody['payment_type'] = 'bank_transfer';
                    $midtransBody['bank_transfer'] = ['bank' => 'bca'];
                }

                $response = Http::withBasicAuth($serverKey, '')
                    ->withoutVerifying()
                    ->acceptJson()
                    ->post($baseUrl, $midtransBody);

                $resData = $response->json();

                // Cek HTTP status maupun status_code internal dari Midtrans
                if ($response->failed() || !in_array($resData['status_code'] ?? '', ['200', '201'])) {
                    $errorMsg = $resData['status_message'] ?? $response->body();
                    throw new \Exception('Midtrans Error: ' . $errorMsg);
                }

                if ($validated['payment_method'] === 'qris') {
                    $qrisPayload = $resData['qr_string']
                        ?? collect($resData['actions'] ?? [])->firstWhere('name', 'generate-qr-code')['url']
                        ?? null;
                } else {
                    $qrisPayload = $resData['va_numbers'][0]['va_number'] ?? '-';
                }
            }

            $transaction = Transaction::create([
                'store_id'       => $store->id,
                'invoice_number' => $invoiceNumber,
                'business_type'  => $store->business_type,
                'table_number'   => $validated['table_number'] ?? null,
                'subtotal'       => $validated['subtotal'],
                'tax_amount'     => $validated['tax_amount'],
                'service_amount' => $validated['service_amount'],
                'grand_total'    => $validated['grand_total'],
                'paid_amount'    => $validated['paid_amount'],
                'change_amount'  => $validated['change_amount'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'qris_payload'   => $qrisPayload,
            ]);

            foreach ($validated['items'] as $item) {
                $detailInfo = collect([
                    $item['selected_variant'] ?? null,
                    !empty($item['note']) ? 'Catatan: ' . $item['note'] : null,
                ])->filter()->implode(' | ');

                TransactionItem::create([
                    'transaction_id'   => $transaction->id,
                    'product_id'       => $item['product_id'],
                    'product_name'     => $item['product_name'],
                    'selected_variant' => $detailInfo ?: null,
                    'qty'              => $item['qty'],
                    'price'            => $item['price'],
                    'total_price'      => $item['total_price'],
                ]);

                // Jika tunai (langsung paid) dan mode retail, langsung potong stok
                if ($isCash && $store->business_type === 'retail') {
                    $product = Product::where('store_id', $store->id)->find($item['product_id']);
                    if ($product && $product->stock >= $item['qty']) {
                        $product->decrement('stock', $item['qty']);
                    }
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaksi dibuat',
                'data'    => $transaction->load('items'),
            ], 201);
        });
    }

    // Endpoint untuk mengecek status pembayaran Midtrans secara real-time (Polling)
    public function checkStatus(Request $request, $invoiceNumber)
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        $transaction = Transaction::with('items')
            ->where('store_id', $store->id)
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();

        if ($transaction->payment_status === 'paid') {
            return response()->json(['status' => 'paid', 'data' => $transaction]);
        }

        $serverKey = config('services.midtrans.server_key');
        $statusUrl = config('services.midtrans.is_production')
            ? "https://api.midtrans.com/v2/{$invoiceNumber}/status"
            : "https://api.sandbox.midtrans.com/v2/{$invoiceNumber}/status";

        $response = Http::withBasicAuth($serverKey, '')
        ->withoutVerifying()
        ->acceptJson()
        ->get($statusUrl);

        if ($response->successful()) {
            $txStatus = $response->json('transaction_status');
            if (in_array($txStatus, ['settlement', 'capture'])) {
                DB::transaction(function () use ($store, $transaction) {
                    $transaction->update(['payment_status' => 'paid']);

                    // Potong stok retail saat QRIS / VA sudah benar-benar lunas
                    if ($store->business_type === 'retail') {
                        foreach ($transaction->items as $item) {
                            $product = Product::where('store_id', $store->id)->find($item->product_id);
                            if ($product && $product->stock >= $item->qty) {
                                $product->decrement('stock', $item->qty);
                            }
                        }
                    }
                });
            }
        }

        return response()->json([
            'status' => $transaction->fresh()->payment_status,
            'data'   => $transaction->fresh('items'),
        ]);
    }
}
