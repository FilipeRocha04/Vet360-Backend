<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    protected $fillable = [
      'user_id','front','back','category','last_reviewed','next_review'
    ];

    public function user()
    {
      return $this->belongsTo(User::class);
    }
}

