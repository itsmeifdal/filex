<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_drive_settings', function (Blueprint $table): void {
            $table->timestamp('structure_synced_at')->nullable()->after('connected_email');
        });
    }

    public function down(): void
    {
        Schema::table('google_drive_settings', function (Blueprint $table): void {
            $table->dropColumn('structure_synced_at');
        });
    }
};
