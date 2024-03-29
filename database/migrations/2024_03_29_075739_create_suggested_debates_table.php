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
        Schema::dropIfExists('suggested_debates');

        Schema::create('suggested_debates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('root_id')->constrained('debate')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('debate')->onDelete('cascade');
            $table->string('title');
            $table->enum('side', ['pros', 'cons']);
            $table->boolean('voting_allowed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggested_debates');
    }
};
