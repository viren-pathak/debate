<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSharedLinksTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('shared_links');

        Schema::create('shared_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debate_id')->constrained('debate')->onDelete('cascade');
            $table->string('link')->unique();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('role');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shared_links');
    }
}
