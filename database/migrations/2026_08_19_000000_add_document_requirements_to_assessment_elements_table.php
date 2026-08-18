<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->unsignedSmallInteger('required_document_count')->nullable()->after('description');
            $table->text('evidence_notes')->nullable()->after('required_document_count');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->dropColumn(['required_document_count', 'evidence_notes']);
        });
    }
};
