<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Districts of the City of Kigali.
 *
 * Modelled as a table rather than a text column so that a user's scope
 * is a foreign key. In the current platform "district" is free text on
 * entities, which allows "Gasabo" and "gasabo " to coexist and silently
 * break filters and permission checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // GASABO
            $table->string('name', 60);             // Gasabo
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
