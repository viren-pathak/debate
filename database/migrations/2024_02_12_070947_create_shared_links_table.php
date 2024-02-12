<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSharedLinksTable extends Migration
{
    public function up()
    {
        Schema::create('shared_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debate_id')->constrained()->onDelete('cascade');
            $table->string('link')->unique();
            $table->unsignedBigInteger('invited_by'); 
            $table->string('role');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shared_links');
    }
}
