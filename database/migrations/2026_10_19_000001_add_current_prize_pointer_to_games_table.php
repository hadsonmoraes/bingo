<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Ponteiro manual do prêmio em disputa ("Avançar prêmio").
            // Nulo = sequência automática: do último prêmio (menor) para o primeiro (principal).
            $table->unsignedBigInteger('current_prize_id')->nullable();
            $table->foreign('current_prize_id')->references('id')->on('prizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['current_prize_id']);
            $table->dropColumn('current_prize_id');
        });
    }
};
