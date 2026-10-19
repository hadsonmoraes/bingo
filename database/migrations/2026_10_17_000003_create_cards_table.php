<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->json('grid');
            $table->string('holder')->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
