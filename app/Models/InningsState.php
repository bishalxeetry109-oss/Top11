<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InningsState extends Model
{
    use HasFactory;

    protected $table = 'innings_state';

    protected $fillable = [
        'match_id',
        'innings_id',
        'striker_id',
        'non_striker_id',
        'bowler_id',
        'last_ball_number',
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

