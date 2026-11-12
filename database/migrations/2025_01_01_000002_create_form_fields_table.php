<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label');
            $table->enum('type', [
                'text', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkbox', 'textarea', 'file',
                'image', 'address', 'country', 'institution', 'level', 'custom',
            ]);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_identity_name')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
