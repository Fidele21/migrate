<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The road register.
 *
 * Not a checklist. A road is not compliant or non-compliant — it is 3.2km
 * long with 1.8km paved, running from one point to another. There is
 * nothing to score, so it is recorded rather than assessed, and it
 * carries no compliance percentage.
 *
 * The register is what road inspections will later be recorded against:
 * a condition survey needs to know which road it is surveying, and this
 * is where that comes from. It also supplies the main corridors that a
 * construction inspection asks about, which are currently a placeholder
 * list in config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roads', function (Blueprint $table) {
            $table->id();

            $table->string('category', 40);          // national, district_1, district_2, specific, main_corridor, local
            $table->string('name');
            $table->string('code', 40)->nullable();  // KN 1 Rd, RN3 and so on

            $table->string('start_point')->nullable();
            $table->string('end_point')->nullable();

            /* Paved, unpaved, cobblestone — or a mix, which is why the
               paved and unpaved lengths are held separately. */
            $table->string('surface', 40)->nullable();

            $table->decimal('length_km', 8, 3)->nullable();
            $table->decimal('paved_km', 8, 3)->nullable();
            $table->decimal('unpaved_km', 8, 3)->nullable();

            /* A road may run through more than one district, so these are
               where it begins rather than where it belongs. */
            $table->string('district', 60)->nullable();
            $table->string('sector', 60)->nullable();

            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'district']);
            $table->unique(['name', 'start_point', 'end_point'], 'roads_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roads');
    }
};