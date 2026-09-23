<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('total_price')->default(0);
            $table->enum('status', ['pending', 'completed', 'void', 'refund'])->default('pending');
            $table->enum('payment_method', ['cash', 'static_qris', 'dynamic_qris'])->default('cash');
            $table->timestamps();
        });

        Schema::create('order_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name'); // Product name at the time of order.
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price')->default(0); // Product price at the time of order.
            $table->decimal('sub_total')->default(0);
            $table->json('variant_selected')->nullable();
            $table->string('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product');
        Schema::dropIfExists('orders');
    }
};
