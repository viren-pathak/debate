<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = ['debate_id', 'vote'];

    public function debate()
    {
        return $this->belongsTo(Debate::class);
    }
}
