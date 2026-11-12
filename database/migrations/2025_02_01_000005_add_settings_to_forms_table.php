<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedInteger('max_responses')->nullable()->after('closes_at');
            $table->boolean('allow_multiple_submissions')->default(false)->after('max_responses');
            $table->boolean('auto_validate')->default(false)->after('allow_multiple_submissions');
            $table->text('confirmation_message')->nullable()->after('auto_validate');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['max_responses', 'allow_multiple_submissions', 'auto_validate', 'confirmation_message']);
        });
    }
};
