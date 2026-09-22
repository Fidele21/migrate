<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faults and their administrative sanctions.
 *
 * The schedule in the Urban Planning Code sets, for each fault, what is
 * owed and what happens next. The same fault costs differently by
 * building category — 300,000 for a Category 2 development started
 * without a permit, 7,000,000 for a Category 5 — so the amount belongs
 * to the pairing rather than to the fault.
 *
 * Three faults carry no fine at all: unauthorised development that does
 * not comply is removed at the defaulter's cost, and construction
 * threatening stability is suspended pending an audit. The amount is
 * therefore nullable, and a sanction without one is not an omission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faults', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 32)->default('construction');
            $table->string('code', 8);
            $table->text('title');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type_code', 'code']);
        });

        Schema::create('fault_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_id')->constrained()->cascadeOnDelete();

            /* What the sanction is keyed to. Most vary by building
               category; obstruction of a public road varies by road
               class, and prohibited activity by the kind of protected
               area. */
            $table->string('scope_kind', 24);   // building_category | road_class | sensitive_area | all
            $table->string('scope', 80);

            $table->string('liable', 48)->nullable();   // defaulter, permittee, issuing authority
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('action');

            $table->timestamps();
            $table->index(['fault_id', 'scope_kind']);
        });

        Schema::create('inspection_faults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fault_id')->constrained();
            $table->foreignId('fault_sanction_id')->nullable()->constrained();

            /* Copied at the time of recording. The schedule may be
               amended; what was found and what was owed on the day must
               not change with it. */
            $table->string('scope', 80)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('action')->nullable();

            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['inspection_id', 'fault_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_faults');
        Schema::dropIfExists('fault_sanctions');
        Schema::dropIfExists('faults');
    }
};
