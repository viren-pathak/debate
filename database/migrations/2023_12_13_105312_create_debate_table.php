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
        Schema::create('debate', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('thesis');
            $table->string('tags');
            $table->string('backgroundinfo');
            $table->string('image')->nullable();
            $table->string('imgname')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debate');
    }
};