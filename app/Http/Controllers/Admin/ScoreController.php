<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\BallByBall;
use App\Models\BatsmanScorecard;
use App\Models\BowlerScorecard;
use App\Models\InningsState;
use App\Models\MatchInnings;
use App\Models\Matches;
use App\Models\MatchPlayer;
use App\Models\MatchScoreSummary;
use App\Models\MatchState;
use App\Services\Cricket\BallProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function index($id)
    {
        $match = Matches::with([
            'players.player',
        ])->findOrFail($id);

        $innings1 = $match->innings->firstWhere('innings_number', 1);
        $innings2 = $match->innings->firstWhere('innings_number', 2);

        if (!$innings1 || !$innings2) {
            $teamAId = $match->team_a_id;
            $teamBId = $match->team_b_id;

            $battingFirstId = $match->batting_first_id ?? $match->toss_winner_id ?? $teamAId;
            $battingSecondId = ((int) $battingFirstId === (int) $teamAId) ? $teamBId : $teamAId;

            $bowlingFirstId = $battingSecondId;
            $bowlingSecondId = $battingFirstId;

            if (!$innings1) {
                $match->innings()->create([
                    'innings_number' => 1,
                    'batting_team_id' => $battingFirstId,
                    'bowling_team_id' => $bowlingFirstId,
                ]);
            }
            if (!$innings2) {
                $match->innings()->create([
                    'innings_number' => 2,
                    'batting_team_id' => $battingSecondId,
                    'bowling_team_id' => $bowlingSecondId,
                ]);
            }

            $match->refresh();
            $innings1 = $match->innings->firstWhere('innings_number', 1);
            $innings2 = $match->innings->firstWhere('innings_number', 2);
        }

        $match->score = $innings1?->runs ?? 0;
        $match->wickets = $innings1?->wickets ?? 0;
        $match->overs = (string) ($innings1?->overs ?? 0);

        $players = $match->players;
        $inningsData = [
            1 => $this->buildInningsData($match, $innings1, $players),
            2 => $this->buildInningsData($match, $innings2, $players),
        ];

        $match->team_a = $match->teamA?->name ?? $match->team_a_id;
        $match->team_b = $match->teamB?->name ?? $match->team_b_id;
        $match->batting_first = $match->battingFirst?->name ?? $match->batting_first_id;
        $match->toss_winner = $match->tossWinner?->name ?? $match->toss_winner_id;
        $match->toss_decision = $match->toss_decision ?? null;

        return view('admin.scores.index', compact('match', 'inningsData'));
    }

    private function buildInningsData(Matches $match, MatchInnings $innings, $players)
    {
        // bowler switching relies on innings_state.bowler_id and match_players.is_bowling

        $battingTeamId = $innings->batting_team_id;
        $bowlingTeamId = $innings->bowling_team_id;

        $state = InningsState::where('innings_id', $innings->id)->first();

        $currentBatsmen = $players
            ->where('team_id', $battingTeamId)
            ->whereIn('player_id', array_filter([$state?->striker_id, $state?->non_striker_id]));

        $currentBatsmen = $currentBatsmen->map(function ($mp) use ($state, $innings) {
            $mp->batting_status = null;
            if ($state && (int) $mp->player_id === (int) $state->striker_id) {
                $mp->batting_status = 'in_strike';
            }
            if ($state && (int) $mp->player_id === (int) $state->non_striker_id) {
                $mp->batting_status = 'in_non_strike';
            }

            $inningsId = $innings->id;

            $sc = BatsmanScorecard::where('innings_id', $inningsId)
                ->where('player_id', $mp->player_id)
                ->first();

            $mp->runs = $sc?->runs ?? 0;
            $mp->balls_faced = $sc?->balls ?? 0;
            $mp->fours = $sc?->fours ?? 0;
            $mp->sixes = $sc?->sixes ?? 0;
            $mp->strike_rate = $sc && $sc->balls > 0 ? ($sc->runs / $sc->balls) * 100 : 0;
            $mp->is_out = (bool) ($sc?->is_out ?? false);
            $mp->how_out = $sc?->how_out;
            return $mp;
        });

        // batsmen list should also be limited to this innings batting team
        $batsmens = $players->where('team_id', $battingTeamId);


        $batsmens = $batsmens->map(function ($mp) use ($innings) {
            $sc = BatsmanScorecard::where('innings_id', $innings->id)
                ->where('player_id', $mp->player_id)
                ->first();

            $mp->runs = $sc?->runs ?? 0;
            $mp->balls_faced = $sc?->balls ?? 0;
            $mp->fours = $sc?->fours ?? 0;
            $mp->sixes = $sc?->sixes ?? 0;
            $mp->strike_rate = $sc && $sc->balls > 0 ? ($sc->runs / $sc->balls) * 100 : 0;
            $mp->is_out = (bool) ($sc?->is_out ?? false);
            $mp->is_batting = !($mp->is_out) && $sc && $sc->balls > 0;
            $mp->batting_status = $mp->player_id === (int) request()->query('striker_id', 0) ? 'in_strike' : $mp->batting_status;
            return $mp;
        });

        // Available batsmen must depend on the selected innings (innings_id)
        // so that 1st innings options and 2nd innings options do not mix.
        $availableBatsmen = $players->where('team_id', $battingTeamId)
            ->whereIn('role', ['BAT', 'WK', 'AR', 'BOWL'])
            ->filter(function ($mp) use ($innings, $state) {
                $inningsId = $innings->id;

                $sc = BatsmanScorecard::where('innings_id', $inningsId)
                    ->where('player_id', $mp->player_id)
                    ->first();

                $isCurrentlyInBat = $state
                    && (
                        (int) $mp->player_id === (int) $state->striker_id
                        || (int) $mp->player_id === (int) $state->non_striker_id
                    );

                $isOut = (bool) ($sc?->is_out ?? false);

                // Show only players from this innings who haven't started batting yet
                // (no scorecard yet OR scorecard has 0 balls) and are not currently in
                // the batting pair and not already out.
                return !$isCurrentlyInBat
                    && !$isOut
                    && (!$sc || (int) ($sc->balls ?? 0) === 0);
            });

        $bowlers = $players->where('team_id', $bowlingTeamId)

            ->whereIn('role', ['BOWL', 'AR']);

        if ($bowlers->isEmpty()) {
            $bowlers = collect([(object) ['id' => 0, 'name' => 'N/A', 'runs_conceded' => 0, 'wickets' => 0, 'economy' => 0]]);
        }

        $currentBowlerMatchPlayerId = null;
        $bowlers = $bowlers->map(function ($mp) use ($innings, $state, &$currentBowlerMatchPlayerId) {
            $playerId = $mp->player_id ?? null;

            $sc = $playerId ? BowlerScorecard::where('innings_id', $innings->id)
                ->where('player_id', $playerId)
                ->first() : null;

            $mp->overs_bowled = $sc?->overs ?? 0;
            $mp->runs_conceded = $sc?->runs_conceded ?? 0;
            $mp->wickets = $sc?->wickets ?? 0;
            $mp->economy = $sc?->economy ?? 0;
            $mp->is_bowling = $state && (int) $mp->player_id === (int) $state->bowler_id;
            if ($mp->is_bowling) {
                $currentBowlerMatchPlayerId = $mp->id;
            }
            return $mp;
        });

        $availableBowlers = $players->where('team_id', $bowlingTeamId)
            ->whereIn('role', ['BOWL', 'AR'])
            ->filter(function ($mp) use ($state) {
                return !($state && (int) $mp->player_id === (int) $state->bowler_id);
            });

        $battingTeamName = \App\Models\Team::where('id', $battingTeamId)->value('name') ?? (string) $battingTeamId;
        $bowlingTeamName = \App\Models\Team::where('id', $bowlingTeamId)->value('name') ?? (string) $bowlingTeamId;

        return [
            'battingTeam' => $battingTeamName,
            'bowlingTeam' => $bowlingTeamName,
            'currentBatsmen' => $currentBatsmen,
            'batsmens' => $batsmens,
            'bowlers' => $bowlers,
            'availableBatsmen' => $availableBatsmen->values(),
            'availableBowlers' => $availableBowlers->values(),
            'currentBowlerId' => $currentBowlerMatchPlayerId,
        ];
    }

    public function setBattingPosition(Request $request, $matchId)
    {
        $request->validate([
            'player_id' => 'required|exists:match_players,id',
            'role' => 'required|in:striker,non-striker',
            'innings' => 'required|integer|in:1,2',
        ]);

        $inningsNumber = (int) $request->input('innings');
        $role = $request->input('role');

        $match = Matches::with('innings')->findOrFail($matchId);
        $innings = $match->innings->firstWhere('innings_number', $inningsNumber);

        if (!$innings) {
            return response()->json(['success' => false, 'message' => 'Innings not initialized'], 400);
        }

        $state = InningsState::where('innings_id', $innings->id)->first();
        if (!$state) {
            $state = InningsState::create([
                'match_id' => $matchId,
                'innings_id' => $innings->id,
                'striker_id' => null,
                'non_striker_id' => null,
                'bowler_id' => null,
                'last_ball_number' => 0,
            ]);
        }

        $matchPlayer = MatchPlayer::where('id', $request->input('player_id'))
            ->where('match_id', $matchId)
            ->first();

        if (!$matchPlayer) {
            return response()->json(['success' => false, 'message' => 'Player not in match'], 400);
        }

            if ($role === 'striker') {
                $state->striker_id = $matchPlayer->player_id;
        } else {
            $state->non_striker_id = $matchPlayer->player_id;
        }

        $state->save();

        return response()->json(['success' => true, 'message' => 'Batting position updated']);
    }

    public function updateRuns(Request $request, $matchId, $playerId)
    {
        // existing code unchanged
        $request->validate([
            'runs' => 'required|integer|min:0|max:6',
            'type' => 'nullable|string',
        ]);

        $match = Matches::with(['innings'])->findOrFail($matchId);
        $state = InningsState::where('match_id', $matchId)->first();
        if (!$state) {
            return response()->json(['success' => false, 'message' => 'Innings state not initialized'], 400);
        }

        $inningsId = $state->innings_id;
        $innings = MatchInnings::findOrFail($inningsId);

        if (!$state->bowler_id) {
            return response()->json(['success' => false, 'message' => 'Bowler not set'], 400);
        }

        $service = app(BallProcessingService::class);
        $result = $service->process(
            $matchId,
            $inningsId,
            $state->striker_id,
            $state->non_striker_id,
            $state->bowler_id,
            (int) $request->runs,
            false
        );

        $strikerMp = MatchPlayer::where('match_id', $matchId)
            ->where('player_id', $result['striker'])
            ->with('player')
            ->first();

        $bowlerMp = MatchPlayer::where('match_id', $matchId)
            ->where('player_id', $result['non_striker'] ? $state->bowler_id : $state->bowler_id)
            ->with('player')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Runs updated',
            'score' => $result['runs'] ?? $innings->runs,
            'wickets' => $result['wickets'] ?? $innings->wickets,
            'striker' => $strikerMp,
            'bowler' => $bowlerMp,
            'swap_strike' => false,
        ]);
    }

    public function ballEvent(Request $request, $matchId)
    {
        $request->validate([
            'type' => 'required|string|in:dot,run,run1,run2,run3,run4,run6,wide,noball,wicket,caught,runout_striker,runout_nonstriker,bowled',
            'runs' => 'required|integer|min:0',
            'innings' => 'required|integer|in:1,2',
        ]);

        $match = Matches::with('innings')->findOrFail($matchId);
        $inningsNumber = (int) $request->input('innings');
        $innings = $match->innings->firstWhere('innings_number', $inningsNumber);

        if (!$innings) {
            return response()->json(['success' => false, 'message' => 'Innings not initialized'], 400);
        }

        $state = InningsState::where('innings_id', $innings->id)->first();
        if (!$state) {
            return response()->json(['success' => false, 'message' => 'Innings state not initialized'], 400);
        }

        if (!$state->bowler_id) {
            return response()->json(['success' => false, 'message' => 'Bowler not set'], 400);
        }

        $type = $request->input('type');
        $runs = (int) $request->input('runs');

        $strikerMp = MatchPlayer::where('match_id', $matchId)
            ->where('player_id', $state->striker_id)
            ->first();
        $nonStrikerMp = MatchPlayer::where('match_id', $matchId)
            ->where('player_id', $state->non_striker_id)
            ->first();
        $bowlerMp = MatchPlayer::where('id', $state->bowler_id)
            ->where('match_id', $matchId)
            ->first();

        if (!$strikerMp) {
            return response()->json(['success' => false, 'message' => 'Striker not found'], 400);
        }
        if (!$bowlerMp) {
            return response()->json(['success' => false, 'message' => 'Bowler not found'], 400);
        }

        $strikerMatchPlayerId = $strikerMp->id;
        $nonStrikerMatchPlayerId = $nonStrikerMp?->id;
        $bowlerMatchPlayerId = $bowlerMp->id;
        $strikerPlayerId = $strikerMp->player_id;
        $bowlerPlayerId = $bowlerMp->player_id;

        $isRunType = in_array($type, ['run', 'run1', 'run2', 'run3', 'run4', 'run6']);
        $isIllegal = in_array($type, ['wide', 'noball']);
        $isWicket = in_array($type, ['bowled', 'caught', 'runout_striker', 'runout_nonstriker', 'wicket']);

        $batsmanRuns = $runs;
        $bowlerRuns = $runs;
        $inningsRuns = $runs;
        $isLegalBall = !$isIllegal && !$isWicket;
        if ($isWicket) {
            $batsmanRuns = 0;
            $bowlerRuns = 0;
            $inningsRuns = 0;
        }

        if ($type === 'wicket') {
            $type = 'bowled';
        }
        if ($type === 'run') {
            $type = 'run' . $runs;
        }

        $wicketType = match ($type) {
            'bowled', 'caught', 'runout_striker', 'runout_nonstriker' => $type,
            default => null,
        };

        return DB::transaction(function () use (
            $matchId, $innings, $state, $inningsNumber, $type, $runs,
            $strikerMp, $nonStrikerMp, $bowlerMp,
            $strikerMatchPlayerId, $nonStrikerMatchPlayerId, $bowlerMatchPlayerId,
            $strikerPlayerId, $bowlerPlayerId,
            $isRunType, $isIllegal, $isWicket, $isLegalBall,
            $batsmanRuns, $bowlerRuns, $inningsRuns, $wicketType
        ) {
            $beforeScore = $innings->runs ?? 0;
            $beforeWickets = $innings->wickets ?? 0;
            $beforeBalls = $innings->balls ?? 0;

            $innings->runs = $beforeScore + $inningsRuns;
            if ($isWicket) {
                $innings->wickets = $beforeWickets + 1;
            }
            if ($isLegalBall) {
                $innings->balls = $beforeBalls + 1;
            }
            $oversInt = intdiv($innings->balls, 6);
            $ballsInOver = $innings->balls % 6;
            $innings->overs = $oversInt . '.' . $ballsInOver;
            $innings->save();

            $batsmanSc = BatsmanScorecard::query()->firstOrCreate(
                ['match_id' => $matchId, 'innings_id' => $innings->id, 'player_id' => $strikerPlayerId],
                ['runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'is_out' => false, 'how_out' => null]
            );

            if ($isLegalBall || $isWicket) {
                $batsmanSc->balls = ($batsmanSc->balls ?? 0) + 1;
            }
            if (!$isIllegal && !$isWicket) {
                $batsmanSc->runs = ($batsmanSc->runs ?? 0) + $batsmanRuns;
            }
            if ($type === 'run4' || $type === 'run6' || $runs === 4 || $runs === 6) {
                if ($runs === 4) $batsmanSc->fours = ($batsmanSc->fours ?? 0) + 1;
                if ($runs === 6) $batsmanSc->sixes = ($batsmanSc->sixes ?? 0) + 1;
            }
            if ($isWicket && $strikerMp) {
                $batsmanSc->is_out = true;
                $batsmanSc->how_out = $wicketType;
            }
            $batsmanSc->save();

            $bowlerSc = BowlerScorecard::query()->firstOrCreate(
                ['match_id' => $matchId, 'innings_id' => $innings->id, 'player_id' => $bowlerPlayerId],
                ['overs' => 0, 'balls' => 0, 'runs_conceded' => 0, 'wickets' => 0, 'maidens' => 0, 'economy' => 0]
            );

            if ($isLegalBall || $isWicket) {
                $bowlerSc->balls = ($bowlerSc->balls ?? 0) + 1;
            }
            $bowlerSc->runs_conceded = ($bowlerSc->runs_conceded ?? 0) + $bowlerRuns;
            if ($isWicket && !in_array($type, ['runout_striker', 'runout_nonstriker'])) {
                $bowlerSc->wickets = ($bowlerSc->wickets ?? 0) + 1;
            }
            $bOversInt = intdiv($bowlerSc->balls, 6);
            $bBallsInOver = $bowlerSc->balls % 6;
            $bowlerSc->overs = $bOversInt + ($bBallsInOver / 10);
            $bowlerSc->economy = $bowlerSc->overs > 0 ? ($bowlerSc->runs_conceded / $bowlerSc->overs) : 0;
            $bowlerSc->save();

            $ballNum = $innings->balls;
            $overNum = intdiv(max($ballNum, 1) - 1, 6) + 1;
            $ballInOver = (($ballNum - 1) % 6) + 1;

            $ball = Ball::create([
                'match_id' => $matchId,
                'innings_id' => $innings->id,
                'striker_id' => $strikerMatchPlayerId,
                'bowler_id' => $bowlerMatchPlayerId,
                'over' => $overNum,
                'ball_number' => $ballInOver,
                'type' => $type,
                'runs' => $runs,
                'is_legal' => $isLegalBall,
            ]);

            BallByBall::create([
                'match_id' => $matchId,
                'innings_id' => $innings->id,
                'over' => $overNum,
                'ball' => $ballInOver,
                'striker_id' => $strikerMatchPlayerId,
                'non_striker_id' => $nonStrikerMatchPlayerId,
                'bowler_id' => $bowlerMatchPlayerId,
                'runs' => $runs,
                'is_wicket' => $isWicket,
                'wicket_type' => $wicketType,
                'type' => $type,
                'commentary' => $this->commentary($type, $runs, $strikerMp->player->name ?? 'Batsman', $bowlerMp->player->name ?? 'Bowler'),
            ]);


            $matchSummary = MatchScoreSummary::firstOrCreate(['match_id' => $matchId]);
            if ($inningsNumber === 1) {
                $matchSummary->innings_1_runs = $innings->runs;
                $matchSummary->innings_1_wickets = $innings->wickets;
                $matchSummary->innings_1_overs = (float) number_format($innings->overs, 1);
            } else {
                $matchSummary->innings_2_runs = $innings->runs;
                $matchSummary->innings_2_wickets = $innings->wickets;
                $matchSummary->innings_2_overs = (float) number_format($innings->overs, 1);
            }
            $matchSummary->save();

            $overComplete = $isLegalBall && ($innings->balls % 6 === 0);
            if ($overComplete) {
                // End of over: swap strike and force bowler reselection
                $tmpPlayerId = $state->striker_id;
                $state->striker_id = $state->non_striker_id;
                $state->non_striker_id = $tmpPlayerId;

                // Trigger UI prompt flow by clearing current bowler
                $state->bowler_id = null;

                // Clear bowling flags
                MatchPlayer::where('match_id', $matchId)->update(['is_bowling' => false]);
            }

            if ($isWicket) {
                // Update batsman scorecards ONLY (no dependency on match_players.batting_status)
                if ($type === 'runout_striker') {
                    if ($strikerMp) {
                        $sc = BatsmanScorecard::query()->where('match_id', $matchId)
                            ->where('innings_id', $innings->id)
                            ->where('player_id', $strikerPlayerId)
                            ->first();

                        if ($sc) {
                            $sc->is_out = true;
                            $sc->how_out = 'run_out';
                            $sc->save();
                        }
                    }
                    $state->striker_id = null;
                } elseif ($type === 'runout_nonstriker') {
                    if ($nonStrikerMp) {
                        $sc = BatsmanScorecard::query()->where('match_id', $matchId)
                            ->where('innings_id', $innings->id)
                            ->where('player_id', $nonStrikerMp->player_id)
                            ->first();

                        if ($sc) {
                            $sc->is_out = true;
                            $sc->how_out = 'run_out';
                            $sc->save();
                        }
                    }
                    // Non-striker is replaced by UI; clear pointer here
                    $state->non_striker_id = null;
                } else {
                    // bowled / caught / wicket
                    if ($strikerMp) {
                        $sc = BatsmanScorecard::query()->where('match_id', $matchId)
                            ->where('innings_id', $innings->id)
                            ->where('player_id', $strikerPlayerId)
                            ->first();

                        if ($sc) {
                            $sc->is_out = true;
                            $sc->how_out = $wicketType;
                            $sc->save();
                        }
                    }
                    $state->striker_id = null;
                }
            } elseif ($isLegalBall && !$isIllegal) {
                if ($runs % 2 !== 0) {
                    $tmpPlayerId = $state->striker_id;
                    $state->striker_id = $state->non_striker_id;
                    $state->non_striker_id = $tmpPlayerId;
                }
            }

            $state->last_ball_number = ($state->last_ball_number ?? 0) + 1;
            $state->save();

            $matchState = MatchState::firstOrCreate([
                'match_id' => $matchId,
                'innings_id' => $innings->id,
            ], [
                'status' => 'live',
                'current_innings' => $inningsNumber,
            ]);
            $matchState->current_over = intdiv($innings->balls, 6);
            $matchState->current_ball = $innings->balls % 6;
            $matchState->last_ball_id = $ball->id;
            $matchState->save();

            $nextBatsmanNeeded = $state->striker_id === null || $state->non_striker_id === null;

            $newStrikerMp = null;
            $newBowlerMp = null;
            if ($state->striker_id) {
                $newStrikerMp = MatchPlayer::where('match_id', $matchId)
                    ->where('player_id', $state->striker_id)
                    ->with('player')
                    ->first();
            }
            if ($state->bowler_id) {
                $newBowlerMp = MatchPlayer::where('id', $state->bowler_id)
                    ->where('match_id', $matchId)
                    ->with('player')
                    ->first();
            }

                return response()->json([
                    'success' => true,
                    'message' => 'Ball recorded',
                    'score' => $innings->runs,
                    'wickets' => $innings->wickets,
                    'overs' => (string) $innings->overs,
                    'striker' => $newStrikerMp,
                    'bowler' => $newBowlerMp,
                    'swap_strike' => !$isWicket && $runs % 2 !== 0 && $isLegalBall,
                    'next_batsman_needed' => $nextBatsmanNeeded,
                    'over_complete' => $overComplete,
                    'innings_completed' => false,
                ]);
        });
    }

    private function commentary(string $type, int $runs, string $batsman, string $bowler): ?string
    {
        $commentaries = [
            'dot' => "Dot ball. {$batsman} defends solidly.",
            'run1' => "Quick single between the wickets. 1 run.",
            'run2' => "They come back for two. Good running between the wickets.",
            'run3' => "Brilliant running! They push for three.",
            'run4' => "FOUR! Beautiful shot by {$batsman}.",
            'run6' => "SIX! Massive hit by {$batsman}! That's gone into the stands!",
            'wide' => "Wide called! Extra run added.",
            'noball' => "No ball! Free hit coming up.",
            'bowled' => "BOWLED! The stumps are shattered! {$batsman} has to go.",
            'caught' => "CAUGHT! {$batsman} skies it and the fielder takes the catch!",
            'runout_striker' => "RUN OUT! {$batsman} is short of the crease!",
            'runout_nonstriker' => "RUN OUT! The non-striker is caught short of the crease!",
        ];

        return $commentaries[$type] ?? "Ball bowled.";
    }

    public function initializeBatting(Request $request, $matchId)
    {
        $match = Matches::with('players')->findOrFail($matchId);
        $innings = $match->innings->firstWhere('innings_number', 1);

        if (!$innings) {
            return response()->json(['success' => false, 'message' => 'Innings not initialized'], 400);
        }

        $state = InningsState::where('innings_id', $innings->id)->first();
        if (!$state) {
            return response()->json(['success' => false, 'message' => 'Innings state not initialized'], 400);
        }

        $battingTeamPlayers = $match->players
            ->where('team_id', $innings->batting_team_id)
            ->where('is_playing', true)
            ->where('batting_status', 'yet_to_bat')
            ->sortBy('id')
            ->values();

        if ($battingTeamPlayers->count() < 2) {
            return response()->json(['success' => false, 'message' => 'Less than 2 players available to bat'], 400);
        }

        $striker = $battingTeamPlayers->first();
        $nextBatsman = $battingTeamPlayers->skip(1)->first();

        $state->striker_id = $striker->player_id;
        $state->non_striker_id = $nextBatsman->player_id;
        $state->save();

        MatchPlayer::where('id', $striker->id)->update(['batting_status' => 'in_strike']);
        MatchPlayer::where('id', $nextBatsman->id)->update(['batting_status' => 'in_non_strike']);

        return response()->json(['success' => true, 'message' => 'Batting initialized successfully']);
    }

    public function setBowler(Request $request, $matchId)
    {
        $request->validate([
            'player_id' => 'required|integer',
            'innings' => 'nullable|integer|in:1,2',
        ]);

        // Frontend passes match_player_id (match_players.id), not players.id
        $matchPlayerId = (int) $request->input('player_id');

        $matchPlayer = MatchPlayer::where('id', $matchPlayerId)->first();
        if (!$matchPlayer) {
            return response()->json(['success' => false, 'message' => 'Selected player is invalid'], 400);
        }

        $playerId = (int) $matchPlayer->player_id;


        $state = InningsState::where('match_id', $matchId)->first();
        if (!$state) {
            return response()->json(['success' => false, 'message' => 'Innings state not initialized'], 400);
        }

        // innings_state.bowler_id foreign key expects match_players.id
        $state->bowler_id = $matchPlayerId;


        // Keep it broad and deterministic: clear all bowling flags, then set the chosen one.
        // (match_players.is_bowling is boolean in DB.)
        MatchPlayer::where('match_id', $matchId)->update(['is_bowling' => false]);

        // Mark selected bowler as bowling in match_players table
        MatchPlayer::where('id', $matchPlayerId)->update(['is_bowling' => true]);


        $state->save();

        return response()->json(['success' => true, 'message' => 'Bowler updated']);
    }

    // ---- Keep rest of original methods (swapStrike, markOut, etc.) ----
}


