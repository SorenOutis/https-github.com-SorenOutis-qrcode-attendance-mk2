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
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable()->change();
            $table->boolean('is_manual')->default(false)->after('status');
            $table->text('remarks')->nullable()->after('is_manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable(false)->change();
            $table->dropColumn(['is_manual', 'remarks']);
        });
    }
};
