<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', fn (Blueprint $table) => $table->unsignedBigInteger('payment_attempt_id')->nullable());
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order')->restrictOnDelete();
            $table->string('gateway_reference', 64)->unique();
            $table->unsignedBigInteger('amount');
            $table->json('snapshot');
            $table->string('status')->default('creating');
            $table->string('stock_state')->default('reserved');
            $table->text('snap_token')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('fraud_status')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['payment_attempt_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reservations');
        Schema::dropIfExists('payment_attempts');
        Schema::table('order', fn (Blueprint $table) => $table->dropColumn('payment_attempt_id'));
    }
};
