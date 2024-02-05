<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDebateEditHistoryTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('debate_edit_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_id')->constrained('debate')->onDelete('cascade');
            $table->foreignId('debate_id')->constrained('debate')->onDelete('cascade');
            $table->foreignId('create_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('edit_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('last_title');
            $table->string('edited_title')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debate_edit_history');
    }
}
