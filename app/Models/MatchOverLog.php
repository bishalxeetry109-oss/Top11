<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchOverLog extends Model
{
    use HasFactory;

    protected $table = 'match_over_logs';

    protected $fillable = [
        'match_id',
        'innings_id',
        'bowler_id',
        'over_number',
        'balls_faced',
        'runs_conceded',
        'wickets',
        'is_completed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function innings()
    {
        return $this->belongsTo(MatchInnings::class, 'innings_id');
    }

    public function bowler()
    {
        return $this->belongsTo(MatchPlayer::class, 'bowler_id');
    }
}

