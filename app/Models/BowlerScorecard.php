<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BowlerScorecard extends Model
{
    use HasFactory;

    protected $table = 'bowler_scorecards';

    protected $fillable = [
        'match_id',
        'innings_id',
        'player_id',
        'overs',
        'balls',
        'runs_conceded',
        'wickets',
        'maidens',
        'economy',
    ];

    protected $casts = [
        'overs' => 'decimal:1',
        'economy' => 'decimal:2',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function innings()
    {
        return $this->belongsTo(MatchInnings::class, 'innings_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}

