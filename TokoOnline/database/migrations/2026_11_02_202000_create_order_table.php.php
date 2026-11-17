```php
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
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status');
            $table->string('nomor')->nullable();
            $table->string('kurir')->nullable();
            $table->string('layanan_antar')->nullable();
            $table->double('biaya_ongkir')->default(0);
            $table->string('estimasi_ongkir')->nullable();
            $table->integer('total_berat')->default(0);
            $table->double('total_harga')->default(0);
            $table->text('alamat')->nullable();
            $table->string('pos')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')->on('customer');

            $table->foreign('user_id')
                ->references('id')->on('user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order');
    }
};