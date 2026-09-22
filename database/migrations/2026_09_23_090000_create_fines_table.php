<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penalties arising from an inspection.
 *
 * A fine is proposed by an inspector, director, senior inspector or the
 * secretary, and confirmed by a Senior or Chief Inspector. Only then is
 * it payable. The Recovery Officer records settlement.
 *
 * Nothing here is deleted: a cancelled fine is marked, so the record of
 * what was proposed and by whom survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference', 40)->nullable()->unique();

            // proposed | confirmed | paid | part_paid | waived | cancelled
            $table->string('status', 20)->default('proposed');

            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('RWF');

            $table->text('reason');
            $table->string('legal_basis', 255)->nullable();
            $table->date('due_date')->nullable();

            $table->foreignId('proposed_by')->constrained('users');
            $table->timestamp('proposed_at')->useCurrent();

            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirm_note')->nullable();

            $table->timestamp('settled_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 80)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->text('payment_note')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'district_id']);
            $table->index('entity_id');
        });

        Schema::create('fine_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fine_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('method', 40)->nullable();
            $table->string('reference', 80)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_payments');
        Schema::dropIfExists('fines');
    }
};
