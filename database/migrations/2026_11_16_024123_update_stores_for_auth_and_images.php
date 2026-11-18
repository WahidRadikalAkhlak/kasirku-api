<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->string('address')->nullable()->after('name');
            $table->string('logo_url')->nullable()->after('primary_color');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'address', 'logo_url']);
        });
    }
};
