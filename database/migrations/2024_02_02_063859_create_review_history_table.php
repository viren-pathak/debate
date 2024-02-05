<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('review_history', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['mark', 'unmark']);
            $table->foreignId('debate_id')->constrained('debate', 'id')->onDelete('cascade');
            $table->foreignId('mark_user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('unmark_user_id')->nullable()->constrained('users', 'id')->onDelete('cascade');
            $table->enum('review', ['Unsupported', 'Not a Claim', 'Unclear', 'Vulgar/Abusive', 'Duplicate', 'Unrelated', 'Move Elsewhere', 'More than one claim']);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('review_history');
    }
}

