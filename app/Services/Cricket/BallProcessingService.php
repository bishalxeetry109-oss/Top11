<?php

namespace App\Services\Cricket;

use App\Models\MatchInnings;
use App\Models\InningsState;
use App\Models\BatsmanScorecard;
use App\Models\BowlerScorecard;
use App\Models\BallByBall;
use Illuminate\Support\Facades\DB;

class BallProcessingService
{
    /**
     * Process a single ball delivery and update DB tables:
     * - match_innings
     * - innings_state
     * - batsman_scorecards (striker)
     * - bowler_scorecards (bowler)
     * - ball_by_ball
     */
    public function process(
        int $matchId,
        int $inningsId,
        int $strikerId,
        ?int $nonStrikerId,
        int $bowlerId,
        int $runs,
        bool $isWicket = false
    ): array {
        $innings = MatchInnings::query()->findOrFail($inningsId);

        $state = InningsState::query()->where('innings_id', $inningsId)->first();
        if (!$state) {
            $state = InningsState::query()->create([
                'match_id' => $matchId,
                'innings_id' => $inningsId,
                'striker_id' => $strikerId,
                'non_striker_id' => $nonStrikerId,
                'bowler_id' => $bowlerId,
                'last_ball_number' => 0,
            ]);
        } else {
            // Keep state synced with controller-provided pointers
            $state->striker_id = $strikerId;
            $state->non_striker_id = $nonStrikerId;
            $state->bowler_id = $bowlerId;
            $state->save();
        }

        // NOTE: UI currently treats wide/noball as legal deliveries (serviceRuns=1)
        // so we count every call as a ball.
        $isLegalBall = true;

        // ===== match_innings =====
        if ($isLegalBall) {
            $innings->balls += 1;
        }
        $innings->runs += $runs;
        if ($isWicket) {
            $innings->wickets += 1;
        }

        $oversInt = intdiv($innings->balls, 6);
        $ballsInOver = $innings->balls % 6;
        $innings->overs = $oversInt . '.' . $ballsInOver;
        $innings->save();

        // ===== batsman_scorecards (striker) =====
        $batsman = BatsmanScorecard::query()->firstOrCreate(
            ['match_id' => $matchId, 'innings_id' => $inningsId, 'player_id' => $strikerId],
            [
                'runs' => 0,
                'balls' => 0,
                'fours' => 0,
                'sixes' => 0,
                'is_out' => false,
                'how_out' => null,
            ]
        );

        $batsman->runs += $runs;
        if ($isLegalBall) {
            $batsman->balls += 1;
        }
        if ($runs === 4) {
            $batsman->fours += 1;
        }
        if ($runs === 6) {
            $batsman->sixes += 1;
        }
        if ($isWicket) {
            $batsman->is_out = true;
            $batsman->how_out = $batsman->how_out;
        }
        $batsman->save();

        // ===== bowler_scorecards (bowler) =====
        $bowler = BowlerScorecard::query()->firstOrCreate(
            ['match_id' => $matchId, 'innings_id' => $inningsId, 'player_id' => $bowlerId],
            [
                'overs' => 0,
                'balls' => 0,
                'runs_conceded' => 0,
                'wickets' => 0,
                'maidens' => 0,
                'economy' => 0,
            ]
        );

        $bowler->runs_conceded += $runs;
        if ($isWicket) {
            $bowler->wickets += 1;
        }
        if ($isLegalBall) {
            $bowler->balls += 1;
        }

        $bOversInt = intdiv($bowler->balls, 6);
        $bBallsInOver = $bowler->balls % 6;
        // DB column is decimal; UI expects “0.1” style.
        $bowler->overs = $bOversInt + ($bBallsInOver / 10);
        $bowler->economy = $bowler->overs > 0 ? ($bowler->runs_conceded / $bowler->overs) : 0;
        $bowler->save();

        // ===== ball_by_ball =====
        $ballCount = $innings->balls; // after increment
        $overNum = intdiv(max($ballCount, 1) - 1, 6) + 1;
        $ballInOver = (($ballCount - 1) % 6) + 1;

        $type = 'run';
        if ($isWicket) {
            $type = 'wicket';
        } elseif ($runs === 0) {
            $type = 'dot';
        }

        BallByBall::query()->create([
            'match_id' => $matchId,
            'innings_id' => $inningsId,
            'over' => $overNum,
            'ball' => $ballInOver,
            'striker_id' => $strikerId,
            'non_striker_id' => $nonStrikerId,
            'bowler_id' => $bowlerId,
            'runs' => $runs,
            'is_wicket' => $isWicket,
            'wicket_type' => $isWicket ? null : null,
            'type' => $type,
            'commentary' => null,
        ]);

        // ===== innings_state =====
        // Strike swap for odd runs on legal balls (simplified).
        if ($isLegalBall && ($runs % 2) !== 0 && !$isWicket) {
            $tmp = $state->striker_id;
            $state->striker_id = $state->non_striker_id;
            $state->non_striker_id = $tmp;
        }

        $state->last_ball_number = ($state->last_ball_number ?? 0) + 1;
        $state->save();

        return [
            'runs' => $innings->runs,
            'wickets' => $innings->wickets,
            'overs' => (string) $innings->overs,
            'striker' => $state->striker_id,
            'non_striker' => $state->non_striker_id,
        ];
    }
}

