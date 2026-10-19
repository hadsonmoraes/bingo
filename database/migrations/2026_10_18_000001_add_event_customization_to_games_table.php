<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('organization_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->date('event_date')->nullable();
            $table->decimal('card_price', 10, 2)->nullable();
            $table->string('theme_color', 30)->default('amber');
            $table->unsignedTinyInteger('na_boa_threshold')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'organization_name',
                'logo_path',
                'event_date',
                'card_price',
                'theme_color',
                'na_boa_threshold',
            ]);
        });
    }
};
