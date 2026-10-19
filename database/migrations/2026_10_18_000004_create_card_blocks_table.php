<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prize_name');
            $table->string('prize_rule_label');
            $table->string('rule', 40);
            $table->json('pattern')->nullable();
            $table->unsignedInteger('position');
            $table->json('grid');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['card_id', 'position']);
            $table->index(['prize_id', 'status']);
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->foreignId('card_block_id')->nullable()->after('card_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_block_id');
        });

        Schema::dropIfExists('card_blocks');
    }
};
