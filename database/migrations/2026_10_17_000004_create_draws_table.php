<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('letter', 1)->nullable();
            $table->unsignedInteger('sequence');
            $table->timestamps();
            $table->unique(['game_id', 'number']);
            $table->index(['game_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
