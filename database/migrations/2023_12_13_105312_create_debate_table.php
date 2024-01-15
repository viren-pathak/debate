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
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade'); // Add this line to connect user with debate
            $table->foreignId('root_id')->nullable()->constrained('debate', 'id')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('debate', 'id')->onDelete('cascade');
            $table->string('side')->nullable(); // Add this line to include the 'side' colum
            $table->string('title');
            $table->string('thesis')->nullable();
            $table->text('tags')->nullable();
            $table->string('backgroundinfo')->nullable();
            $table->string('image')->nullable();
            $table->string('imgname')->nullable();
            $table->timestamps();
            $table->string('isDebatePublic')->nullable();
            $table->string('isType')->nullable();
            $table->boolean('voting_allowed')->default(false);
            $table->integer('total_votes')->default(0);
            
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