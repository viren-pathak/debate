<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class debate extends Model
{
    use HasFactory;

    protected $table = 'debate';

    protected $fillable = [
        'title',
        'thesis',
        'tags',
        'backgroundinfo',
        'image',
        'imgname'
    ];
}
