<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebateEditHistory extends Model
{
    use HasFactory;

    protected $table = 'debate_edit_history';

    protected $fillable = [
        'root_id',
        'debate_id',
        'create_user_id',
        'edit_user_id',
        'last_title',
        'edited_title',
    ];
}
