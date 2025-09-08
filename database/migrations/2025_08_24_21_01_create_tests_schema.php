<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('difficulty', 32)->default('Beginner'); 
            $table->integer('estimated_minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('test_id');
            $table->string('type', 16); // mcq|code
            $table->string('title');
            $table->text('prompt')->nullable();
            $table->boolean('multiple')->nullable();      
            $table->string('language', 32)->nullable();  
            $table->text('starter')->nullable();        
            $table->jsonb('tests_json')->nullable();   
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
        });

        Schema::create('options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_id');
            $table->string('text');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        });

        Schema::create('correct_options', function (Blueprint $table) {
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id');

            $table->primary(['question_id','option_id']);
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            $table->foreign('option_id')->references('id')->on('options')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correct_options');
        Schema::dropIfExists('options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('tests');
    }
};
