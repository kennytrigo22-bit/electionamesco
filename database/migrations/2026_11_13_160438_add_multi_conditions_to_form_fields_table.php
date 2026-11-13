<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('condition_value');
            $table->string('condition_logic')->default('and')->after('conditions');
        });

        foreach (DB::table('form_fields')->whereNotNull('condition_field_id')->get() as $field) {
            DB::table('form_fields')->where('id', $field->id)->update([
                'conditions' => json_encode([
                    ['field_id' => $field->condition_field_id, 'value' => $field->condition_value],
                ]),
            ]);
        }

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condition_field_id');
            $table->dropColumn('condition_value');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->foreignId('condition_field_id')->nullable()->after('is_identity_name')->constrained('form_fields')->nullOnDelete();
            $table->string('condition_value')->nullable()->after('condition_field_id');
        });

        foreach (DB::table('form_fields')->whereNotNull('conditions')->get() as $field) {
            $conditions = json_decode($field->conditions, true) ?: [];
            if ($conditions) {
                DB::table('form_fields')->where('id', $field->id)->update([
                    'condition_field_id' => $conditions[0]['field_id'] ?? null,
                    'condition_value' => $conditions[0]['value'] ?? null,
                ]);
            }
        }

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'condition_logic']);
        });
    }
};
