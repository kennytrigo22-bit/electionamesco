<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('form_submission_id')->nullable()->constrained('form_submissions');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->uuid('token')->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_voters');
    }
};
