<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les votes sont intentionnellement anonymes : aucune clé étrangère vers
        // election_voters, afin de préserver le secret du bulletin. L'unicité du
        // vote est garantie par election_voters.used_at (mise à jour atomique).
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_id')->constrained('ballots')->cascadeOnDelete();
            $table->json('choice_data');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
