<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->json('shipping_destination')->nullable();
            $table->json('shipping_quote')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order', fn (Blueprint $table) => $table->dropColumn(['shipping_destination', 'shipping_quote']));
    }
};
