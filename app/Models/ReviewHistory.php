<?php
    
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewHistory extends Model
{
    use HasFactory;

    protected $table = 'review_history';

    protected $fillable = [
        'status',
        'debate_id',
        'mark_user_id',
        'unmark_user_id',
        'review',
        'reason',
    ];

    public function debate()
    {
        return $this->belongsTo(Debate::class);
    }

    public function markUser()
    {
        return $this->belongsTo(User::class, 'mark_user_id');
    }

    public function unmarkUser()
    {
        return $this->belongsTo(User::class, 'unmark_user_id');
    }
}
