<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free editing of correspondence.
 *
 * A letter begins as generated text assembled from the standard clauses.
 * The moment it is opened in the editor that text is frozen into
 * content_html and becomes ordinary editable content — any wording, any
 * formatting, imported material, images.
 *
 * is_manual records that the link to the inspection has been broken, so
 * a reviewer can tell a generated letter from an edited one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('content_html')->nullable()->after('body_html');
            $table->boolean('is_manual')->default(false)->after('content_html');
            $table->timestamp('edited_at')->nullable()->after('is_manual');
            $table->foreignId('edited_by')->nullable()->after('edited_at')
                  ->constrained('users')->nullOnDelete();
            $table->string('imported_from', 200)->nullable()->after('edited_by');
        });

        Schema::create('document_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 200)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_assets');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['edited_by']);
            $table->dropColumn(['content_html', 'is_manual', 'edited_at', 'edited_by', 'imported_from']);
        });
    }
};
