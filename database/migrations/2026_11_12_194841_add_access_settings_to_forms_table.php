<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->enum('access', ['public', 'restricted'])->default('public')->after('status');
            $table->boolean('collect_email')->default(true)->after('confirmation_message');
            $table->boolean('one_response_per_person')->default(true)->after('collect_email');
            $table->boolean('show_progress_bar')->default(true)->after('one_response_per_person');
        });

        DB::table('forms')->update([
            'one_response_per_person' => DB::raw('CASE WHEN allow_multiple_submissions = 1 THEN 0 ELSE 1 END'),
        ]);

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('allow_multiple_submissions');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('allow_multiple_submissions')->default(false)->after('max_responses');
        });

        DB::table('forms')->update([
            'allow_multiple_submissions' => DB::raw('CASE WHEN one_response_per_person = 1 THEN 0 ELSE 1 END'),
        ]);

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['access', 'collect_email', 'one_response_per_person', 'show_progress_bar']);
        });
    }
};
