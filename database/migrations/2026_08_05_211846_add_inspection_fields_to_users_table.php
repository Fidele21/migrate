<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspection-specific fields on users.
 *
 * district_id NULL means the user operates across all districts —
 * Chief Inspector, Senior Inspector, Administrator. A Director,
 * Lead Inspector or Inspector is bound to exactly one district.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()->after('email')
                  ->constrained('districts')->nullOnDelete();
            $table->string('employee_number', 40)->nullable()->after('district_id');
            $table->string('phone', 30)->nullable()->after('employee_number');
            $table->string('signature_path')->nullable()->after('phone');
            $table->timestamp('signature_registered_at')->nullable()->after('signature_path');
            $table->boolean('is_active')->default(true)->after('signature_registered_at');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropColumn([
                'district_id', 'employee_number', 'phone', 'signature_path',
                'signature_registered_at', 'is_active', 'last_login_at',
            ]);
        });
    }
};
