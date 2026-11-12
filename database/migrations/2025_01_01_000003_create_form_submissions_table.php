<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->enum('status', ['pending', 'validated', 'rejected', 'suspended'])->default('pending');
            $table->enum('eligibility', ['eligible', 'non_eligible', 'verification'])->nullable();
            $table->timestamp('submitted_at');
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
