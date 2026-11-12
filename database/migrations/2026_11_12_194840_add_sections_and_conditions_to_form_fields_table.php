<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->foreignId('form_section_id')->nullable()->after('form_id')->constrained('form_sections')->nullOnDelete();
            $table->foreignId('condition_field_id')->nullable()->after('is_identity_name')->constrained('form_fields')->nullOnDelete();
            $table->string('condition_value')->nullable()->after('condition_field_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_section_id');
            $table->dropConstrainedForeignId('condition_field_id');
            $table->dropColumn('condition_value');
        });
    }
};
