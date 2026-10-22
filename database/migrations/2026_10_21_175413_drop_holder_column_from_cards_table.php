<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove a coluna legada "holder" — substituída por buyer_name /
     * buyer_phone e nunca preenchida desde então.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('holder');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('holder')->nullable();
        });
    }
};
