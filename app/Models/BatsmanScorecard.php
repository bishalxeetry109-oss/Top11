<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatsmanScorecard extends Model
{
    use HasFactory;

    protected $table = 'batsman_scorecards';

    protected $fillable = [
        'match_id',
        'innings_id',
        'player_id',
        'runs',
        'balls',
        'fours',
        'sixes',
        'is_out',
        'how_out',
    ];

    protected $casts = [
        'is_out' => 'boolean',
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

