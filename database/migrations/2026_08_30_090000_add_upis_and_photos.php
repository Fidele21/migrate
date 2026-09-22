<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple parcels per premises, and inspection photographs.
 *
 * A single premises frequently spans more than one parcel — a petrol
 * station forecourt and its shop may sit on separate UPIs. Holding
 * them in their own table keeps each one whole and searchable, rather
 * than concatenated into a single field.
 *
 * Photographs are stored on disk with a path recorded here. The
 * previous platform held them as base64 inside the database, which
 * bloated every backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_upis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('upi', 60);
            $table->boolean('is_primary')->default(false);
            $table->string('note', 160)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'upi']);
            $table->index('upi');
        });

        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 200)->nullable();
            $table->string('caption', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'sort_order']);
        });

        // Move existing single UPIs into the new table.
        if (Schema::hasColumn('entities', 'upi')) {
            $rows = \Illuminate\Support\Facades\DB::table('entities')
                ->whereNotNull('upi')->where('upi', '<>', '')
                ->get(['id', 'upi']);

            foreach ($rows as $row) {
                \Illuminate\Support\Facades\DB::table('entity_upis')->insert([
                    'entity_id'  => $row->id,
                    'upi'        => trim($row->upi),
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_photos');
        Schema::dropIfExists('entity_upis');
    }
};
