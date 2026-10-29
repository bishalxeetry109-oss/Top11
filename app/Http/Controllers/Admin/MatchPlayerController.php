<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;

class MatchPlayerController extends Controller
{
    /**
     * Fetch match player details (for edit modal).
     */
    public function show(MatchPlayer $matchPlayer)
    {
        $matchPlayer->loadMissing(['player', 'team']);

        return response()->json([
            'success' => true,
            'matchPlayer' => [
                'id' => $matchPlayer->id,
                'player_name' => $matchPlayer->player?->name,
                'team_name' => $matchPlayer->team?->name,
                'role' => $matchPlayer->role,
                'credit' => $matchPlayer->credit,
                'points' => $matchPlayer->points,
            ],
        ]);
    }

    /**
     * Update role/credit for a match player.
     */
    public function update(Request $request, MatchPlayer $matchPlayer)
    {
        $request->validate([
            'role' => 'required|in:WK,BAT,AR,BOWL',
            'credit' => 'required|numeric|min:8|max:15',
        ]);

        $matchPlayer->update([
            'role' => $request->input('role'),
            'credit' => $request->input('credit'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Player updated successfully',
        ]);
    }

    /**
     * Delete a player from match (remove only match_players row).
     */
    public function destroy(MatchPlayer $matchPlayer)
    {
        $matchPlayer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Player removed from match',
        ]);
    }
}

