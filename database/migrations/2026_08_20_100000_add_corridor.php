<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a site stands on a main corridor.
 *
 * A building on a main corridor is held to requirements a building on a
 * back lane is not — setbacks, frontage, hoarding, the state of the
 * pavement alongside. Which corridor it is matters, because enforcement
 * along a road is planned as a stretch rather than premises by premises.
 *
 * Recorded against the visit rather than the premises: a road may be
 * reclassified, and what mattered on the day should not change with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('inspections', 'on_main_corridor')) {
                $table->boolean('on_main_corridor')->nullable()->after('building_category');
            }
            if (! Schema::hasColumn('inspections', 'corridor_road')) {
                $table->string('corridor_road', 120)->nullable()->after('on_main_corridor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['on_main_corridor', 'corridor_road']);
        });
    }
};
