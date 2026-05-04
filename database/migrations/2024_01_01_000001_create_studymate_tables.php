<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('color', 7)->default('#16a34a');
            $table->integer('progress')->default(0);
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_id');
            $table->string('filename', 255);
            $table->string('original_name', 255);
            $table->longText('ai_content')->nullable();
            $table->string('mode', 30)->default('ringkasan');
            $table->string('language', 10)->default('malay');
            $table->timestamps();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });

        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id');
            $table->unsignedBigInteger('subject_id');
            $table->text('soalan');
            $table->text('jawapan');
            $table->timestamps();
            $table->foreign('note_id')->references('id')->on('notes')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('subjects');
    }
};
