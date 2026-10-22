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
        Schema::table('games', function (Blueprint $table) {
            // Código impresso no canhoto destacável: 'barcode' (Code128,
            // leitores de caixa) ou 'qrcode' (leitura por celular).
            $table->string('stub_code_type', 10)->default('barcode')->after('theme_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('stub_code_type');
        });
    }
};
