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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('venue_table_id')->after('payment_url')->nullable()->constrained()->nullOnDelete();
            $table->string('order_source')->nullable()->after('venue_table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['venue_table_id']);
            $table->dropColumn([
                'venue_table_id',
                'order_source',
            ]);
        });
    }
};
