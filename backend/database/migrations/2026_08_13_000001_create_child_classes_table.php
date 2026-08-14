<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_classes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['kindergarten_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_classes');
    }
};
