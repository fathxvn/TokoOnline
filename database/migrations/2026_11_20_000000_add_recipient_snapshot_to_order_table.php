<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('nama_penerima')->nullable();
            $table->string('email_penerima')->nullable();
            $table->string('hp_penerima', 13)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn(['nama_penerima', 'email_penerima', 'hp_penerima']);
        });
    }
};
