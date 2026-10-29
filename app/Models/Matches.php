<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\MatchInnings;
use App\Models\InningsState;
use App\Models\MatchState;
use App\Models\MatchScoreSummary;
use App\Models\Ball;
use App\Models\BallByBall;
use App\Models\BatsmanScorecard;
use App\Models\BowlerScorecard;

class Matches extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport',
        'venue',
        'team_a_id',
        'team_b_id',
        'toss_winner_id',
        'toss_winner_batting_id',

        'overs_limit',
        'status',
        'current_innings',
        'start_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'current_innings' => 'integer',
    ];

    /* =========================
        TEAM RELATIONS
    ========================= */

    public function teamA()
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB()
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    public function tossWinner()
    {
        return $this->belongsTo(Team::class, 'toss_winner_id');
    }

    public function battingFirst()
    {
        return $this->belongsTo(Team::class, 'batting_first_id');
    }

    /* =========================
        MATCH DATA
    ========================= */

    public function players()
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function contests()
    {
        return $this->hasMany(Contest::class, 'match_id');
    }
    public function innings()
    {
        return $this->hasMany(MatchInnings::class, 'match_id');
    }


    public function inningsStates()
    {
        return $this->hasMany(InningsState::class, 'match_id');
    }

    public function matchStates()
    {
        return $this->hasMany(MatchState::class, 'match_id');
    }

    public function matchScoreSummary()
    {
        return $this->hasOne(MatchScoreSummary::class, 'match_id');
    }

    public function balls()
    {
        return $this->hasMany(Ball::class, 'match_id');
    }

    public function ballByBalls()
    {
        return $this->hasMany(BallByBall::class, 'match_id');
    }

    public function batsmanScorecards()
    {
        return $this->hasMany(BatsmanScorecard::class, 'match_id');
    }

    public function bowlerScorecards()
    {
        return $this->hasMany(BowlerScorecard::class, 'match_id');
    }
    /* =========================
        STATUS HELPERS
    ========================= */

    public function isUpcoming()
    {
        return $this->status === 'upcoming';
    }

    public function isLive()
    {
        return $this->status === 'live';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /* =========================
        CURRENT PLAYERS (IMPORTANT FIX)
        → DO NOT use A/B logic anymore
        → use innings_state table instead
    ========================= */

    public function currentBatsmen()
    {
        return $this->players()
            ->whereIn('batting_status', ['in_strike', 'in_non_strike']);
    }

    public function currentBowler()
    {
        return $this->players()
            ->where('is_bowling', 1)
            ->first();
    }
}