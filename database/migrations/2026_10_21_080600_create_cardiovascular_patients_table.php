<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cardiovascular_patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patientid')->unique();
            $table->integer('age');
            $table->tinyInteger('gender');
            $table->integer('chestpain');
            $table->integer('restingBP');
            $table->integer('serumcholestrol');
            $table->tinyInteger('fastingbloodsugar');
            $table->integer('restingrelectro');
            $table->integer('maxheartrate');
            $table->tinyInteger('exerciseangia');
            $table->decimal('oldpeak', 4, 1);
            $table->integer('slope');
            $table->integer('noofmajorvessels');
            $table->tinyInteger('target');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cardiovascular_patients');
    }
};
