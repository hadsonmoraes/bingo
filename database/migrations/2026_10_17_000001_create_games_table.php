<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('number_min')->default(1);
            $table->unsignedSmallInteger('number_max')->default(75);
            $table->boolean('use_letters')->default(true);
            $table->string('card_format')->default('75'); // 75 | 90 | custom
            $table->string('status')->default('setup'); // setup | in_progress | paused | finished
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
