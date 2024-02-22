<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamInviteLinksTable extends Migration
{
    public function up()
    {
        Schema::create('team_invite_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('link')->unique();
            $table->string('invite_message')->nullable();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('team_invite_links');
    }
}
