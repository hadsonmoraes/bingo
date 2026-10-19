<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->index();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone', 40)->nullable();
            $table->unsignedInteger('batch')->default(1);
            $table->timestamp('printed_at')->nullable();
            $table->json('prize_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'buyer_name',
                'buyer_phone',
                'batch',
                'printed_at',
                'prize_snapshot',
            ]);
        });
    }
};
