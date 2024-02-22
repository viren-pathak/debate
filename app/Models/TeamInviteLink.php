<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamInviteLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', 
        'link', 
        'role',
        'invite_message',
        'invited_by'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
