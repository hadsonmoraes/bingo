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
            // Palavra-tema de 5 letras que substitui "BINGO" no painel
            // de números e nas cartelas (nula = usa "BINGO").
            $table->string('letters_word', 10)->nullable()->after('use_letters');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('letters_word');
        });
    }
};
