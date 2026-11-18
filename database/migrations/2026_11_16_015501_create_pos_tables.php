<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Profil Toko & Mode Dinamis
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('business_type', ['retail', 'coffeeShop', 'restaurant'])->default('retail');
            $table->decimal('tax_percent', 5, 2)->default(0);      // PPN (%)
            $table->decimal('service_charge', 5, 2)->default(0);   // Service Charge (%)
            $table->string('primary_color')->default('#008080');   // Hex warna tema
            $table->timestamps();
        });

        // 2. Tabel Produk / Menu
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->enum('business_type', ['retail', 'coffeeShop', 'restaurant']);
            $table->string('category');
            $table->string('barcode')->nullable()->index(); // Khusus retail / minimarket
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->json('variants')->nullable(); // Menyimpan array varian: ["Ice", "Hot"] atau ["Pcs", "Dus"]
            $table->timestamps();
        });

        // 3. Tabel Header Transaksi (Siap untuk Tunai, Transfer, & QRIS)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->enum('business_type', ['retail', 'coffeeShop', 'restaurant']);
            $table->string('table_number')->nullable(); // Khusus restoran
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('service_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('payment_method'); // cash, transfer, qris
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('paid');
            $table->text('qris_payload')->nullable(); // Nanti diisi string QRIS dari Midtrans/Xendit
            $table->timestamps();
        });

        // 4. Tabel Detail Item Transaksi
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('selected_variant')->nullable();
            $table->integer('qty');
            $table->decimal('price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
    }
};
