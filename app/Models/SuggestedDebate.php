<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuggestedDebate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'root_id',
        'parent_id',
        'title',
        'side',
        'voting_allowed'
    ];

    public function parent()
    {
        return $this->belongsTo(Debate::class, 'parent_id');
    }

}
