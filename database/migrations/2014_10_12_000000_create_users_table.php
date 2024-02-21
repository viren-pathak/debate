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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('profile_picture')->nullable();
            $table->string('email');
            $table->string('username')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('role')->default('user');
            $table->boolean('isProfilePrivate')->default(false);
            $table->integer('total_claims')->default(0);
            $table->integer('total_votes')->default(0);
            $table->integer('total_comments')->default(0);
            $table->integer('total_contributions')->default(0);
            $table->integer('total_received_thanks')->default(0);
            $table->text('biography')->nullable();
            $table->string('password');
            $table->string('verification_token')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
};
