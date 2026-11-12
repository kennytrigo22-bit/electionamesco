<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->boolean('allow_blank')->default(true)->after('max_choices');
            $table->boolean('allow_abstention')->default(false)->after('allow_blank');
        });
    }

    public function down(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->dropColumn(['allow_blank', 'allow_abstention']);
        });
    }
};
