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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('team_handle');
            $table->foreignId('team_creator_id')->constrained('users')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->string('team_url')->nullable();
            $table->string('team_picture')->nullable();
            $table->boolean('adminOnlyInvite')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();
    
        // Drop the teams table
        Schema::dropIfExists('teams');
    
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
    
};
