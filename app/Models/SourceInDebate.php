<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceInDebate extends Model
{
    use HasFactory;

    protected $table = 'sources_in_debate';

    protected $fillable = [
        'root_id',
        'debate_id',
        'debate_title',
        'display_text',
        'link',
    ];
}
