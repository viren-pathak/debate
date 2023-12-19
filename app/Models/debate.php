<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class debate extends Model
{
    use HasFactory;

    protected $table = 'debate';

    protected $fillable = [
        'parent_id',
        'title',
        'side',
        'thesis',
        'tags',
        'backgroundinfo',
        'image',
        'imgname',
        'isDebatePublic',
        'isType'
    ];

    public function children()
    {
        return $this->hasMany(Debate::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Debate::class, 'parent_id');
    }

    public function pros()
    {
        return $this->children()->where('side', 'pros');
    }

    public function cons()
    {
        return $this->children()->where('side', 'cons');
    }
}
