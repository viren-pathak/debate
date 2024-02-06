<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSourcesInDebateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sources_in_debate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_id')->constrained('debate')->onDelete('cascade');
            $table->foreignId('debate_id')->constrained('debate')->onDelete('cascade');
            $table->string('debate_title');
            $table->text('display_text');
            $table->string('link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sources_in_debate');
    }
}
