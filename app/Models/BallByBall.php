<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BallByBall extends Model
{
    use HasFactory;

    protected $table = 'ball_by_ball';

    protected $fillable = [
        'match_id',
        'innings_id',
        'over',
        'ball',
        'striker_id',
        'non_striker_id',
        'bowler_id',
        'runs',
        'is_wicket',
        'wicket_type',
        'dismissed_player_id',
        'fielder_id',
        'assisting_fielder_id',
        'type',
        'commentary',
    ];

    protected $casts = [
        'is_wicket' => 'boolean',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function innings()
    {
        return $this->belongsTo(MatchInnings::class, 'innings_id');
    }

    public function striker()
    {
        return $this->belongsTo(MatchPlayer::class, 'striker_id');
    }

    public function nonStriker()
    {
        return $this->belongsTo(MatchPlayer::class, 'non_striker_id');
    }

    public function bowler()
    {
        return $this->belongsTo(MatchPlayer::class, 'bowler_id');
    }
}

