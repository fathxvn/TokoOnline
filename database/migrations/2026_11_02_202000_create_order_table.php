<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customer')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('user')->onDelete('cascade');
            $table->string('status');
            $table->string('noresi')->nullable();
            $table->string('kurir')->nullable();
            $table->string('layanan_ongkir')->nullable();
            $table->double('biaya_ongkir')->default(0);
            $table->string('estimasi_ongkir')->nullable();
            $table->integer('total_berat')->default(0);
            $table->double('total_harga')->default(0);
            $table->text('alamat')->nullable();
            $table->string('pos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order');
    }
};
