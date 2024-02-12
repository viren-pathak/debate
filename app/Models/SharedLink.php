<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'debate_id',
        'link',
        'invited_by',
        'role',
    ];

    public function debate()
    {
        return $this->belongsTo(Debate::class);
    }
}
