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
        Schema::create('accreditation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_element_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('uploader_name', 150);
            $table->string('uploader_unit', 150);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->string('drive_file_id')->unique();
            $table->text('drive_web_view_link')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accreditation_documents');
    }
};
