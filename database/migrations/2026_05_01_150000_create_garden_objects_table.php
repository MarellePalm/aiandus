<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garden_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garden_plan_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('name')->default('Aiaelement');
            $table->unsignedSmallInteger('x')->default(0)->comment('Planner X position in pixels');
            $table->unsignedSmallInteger('y')->default(0)->comment('Planner Y position in pixels');
            $table->unsignedSmallInteger('width')->default(200)->comment('Object width in centimeters');
            $table->unsignedSmallInteger('height')->default(200)->comment('Object height in centimeters');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garden_objects');
    }
};
