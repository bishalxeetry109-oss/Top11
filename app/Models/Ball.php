<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ball extends Model
{
    use HasFactory;

    protected $table = 'balls';

    protected $fillable = [
        'match_id',
        'innings_id',
        'striker_id',
        'bowler_id',
        'over',
        'ball_number',
        'type',
        'runs',
        'is_legal',
    ];

    protected $casts = [
        'is_legal' => 'boolean',
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

    public function bowler()
    {
        return $this->belongsTo(MatchPlayer::class, 'bowler_id');
    }
}

