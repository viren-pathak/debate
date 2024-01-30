<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebateRole extends Model
{
    protected $fillable = [
        'user_id',
        'root_id', 
        'role'];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define the relationship with the Debate model
    public function debate()
    {
        return $this->belongsTo(Debate::class);
    }
}

