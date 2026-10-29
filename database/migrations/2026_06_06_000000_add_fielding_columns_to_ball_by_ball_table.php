<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ball_by_ball', function (Blueprint $table) {
            // Who was dismissed (for wickets)
            $table->foreignId('dismissed_player_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            // Who fielded/took part in dismissal (catchers/fienlders/keepers)
            $table->foreignId('fielder_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            $table->foreignId('assisting_fielder_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            // Normalize wicket types: bowled, caught, lbw, stumped, runout, hit_wicket, retired_hurt
            // (already exists as string, we keep it)
        });
    }

    public function down(): void
    {
        Schema::table('ball_by_ball', function (Blueprint $table) {
            $table->dropForeign(['dismissed_player_id']);
            $table->dropForeign(['fielder_id']);
            $table->dropForeign(['assisting_fielder_id']);

            $table->dropColumn(['dismissed_player_id', 'fielder_id', 'assisting_fielder_id']);
        });
    }
};

