<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garden_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Minu aed');
            $table->unsignedSmallInteger('width')->default(1200)->comment('Garden width in centimeters');
            $table->unsignedSmallInteger('height')->default(800)->comment('Garden height in centimeters');
            $table->string('unit', 10)->default('cm');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garden_plans');
    }
};
