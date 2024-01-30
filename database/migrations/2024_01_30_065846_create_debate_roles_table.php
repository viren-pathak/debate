<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDebateRolesTable extends Migration
{
    public function up()
    {
        Schema::create('debate_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_id')->constrained('debate', 'id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->enum('role', ['owner', 'editor', 'writer', 'suggester', 'viewer'])->default('suggester');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('debate_roles');
    }
}

