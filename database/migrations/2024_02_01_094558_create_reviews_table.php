<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mark_user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('root_id')->constrained('debate')->onDelete('cascade');
            $table->foreignId('debate_id')->constrained('debate', 'id')->onDelete('cascade');
            $table->string('review', 50); 
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
}
