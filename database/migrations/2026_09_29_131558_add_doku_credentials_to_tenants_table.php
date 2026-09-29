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
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('payment_client_id')->nullable()->after('business_email');
            $table->text('payment_api_key')->nullable()->after('payment_client_id');
            $table->text('payment_secret_key')->nullable()->after('payment_api_key');
            $table->text('rsa_private_key')->nullable()->after('payment_secret_key');
            $table->text('rsa_public_key')->nullable()->after('rsa_private_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('payment_client_id');
            $table->dropColumn('payment_api_key');
            $table->dropColumn('payment_secret_key');
            $table->dropColumn('rsa_private_key');
            $table->dropColumn('rsa_public_key');
        });
    }
};
