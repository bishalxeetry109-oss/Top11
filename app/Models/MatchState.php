<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchState extends Model
{
    use HasFactory;

    protected $table = 'match_states';

    protected $fillable = [
        'match_id',
        'innings_id',
        'status',
        'current_innings',
        'last_ball_id',
        'current_over',
        'current_ball',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function innings()
    {
        return $this->belongsTo(MatchInnings::class, 'innings_id');
    }

    public function lastBall()
    {
        return $this->belongsTo(BallByBall::class, 'last_ball_id');
    }
}

