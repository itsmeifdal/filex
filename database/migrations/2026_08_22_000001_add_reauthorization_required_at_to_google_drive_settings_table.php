<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_drive_settings', function (Blueprint $table): void {
            $table->timestamp('reauthorization_required_at')->nullable()->after('structure_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('google_drive_settings', function (Blueprint $table): void {
            $table->dropColumn('reauthorization_required_at');
        });
    }
};
