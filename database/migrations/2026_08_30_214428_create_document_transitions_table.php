<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable record of every movement a document makes.
 *
 * Insert only. Never updated, never deleted, no soft delete. When a
 * station owner disputes an enforcement notice two years from now,
 * this table is the answer to "who did what, and when".
 *
 * Note the absence of updated_at. A row that can be updated is not
 * an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->foreignId('actor_id')->constrained('users');
            $table->string('actor_role', 60);

            $table->text('comment')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transitions');
    }
};
