<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_voters', function (Blueprint $table) {
            $table->foreignId('ballot_id')->nullable()->after('election_id')->constrained('ballots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('election_voters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ballot_id');
        });
    }
};
