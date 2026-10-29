<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;

class TemporaryTestDataSeeder extends Seeder
{
    /**
     * TEMPORARY SEEDER for UI testing only.
     * Seeds 4 teams and 11 players per team.
     * Role distribution per team:
     * - BAT: 5
     * - BOWL: 3
     * - AR: 2
     * - WK: 1
     */
    public function run(): void
    {
        $teams = collect([
            'India',
            'Nepal',
            'New Zealand',
            'Australia',
        ])->map(fn ($name) => Team::firstOrCreate(['name' => $name]));

        foreach ($teams as $team) {
            $this->seedTeam11Players((int) $team->id, (string) $team->name);
        }
    }

    private function seedTeam11Players(int $teamId, string $teamName): void
    {
        // Roles: BAT x5, BOWL x3, AR x2, WK x1 (total 11)
        $roles = array_merge(
            array_fill(0, 5, 'BAT'),
            array_fill(0, 3, 'BOWL'),
            array_fill(0, 2, 'AR'),
            array_fill(0, 1, 'WK')
        );

        // Credits spread (must fit players.credit float column)
        $credits = [10.0, 9.5, 11.0, 9.0, 8.5, 12.0, 10.5, 9.5, 11.5, 8.0, 13.0];

        // Use country-like real names (still synthetic but realistic).
        // Map team -> 11 plausible cricket player names.
        $namePoolByTeam = [
            'India' => [
                'Virat Kohli', 'Rohit Sharma', 'KL Rahul', 'Shreyas Iyer', 'Suryakumar Yadav',
                'Ravindra Jadeja', 'Hardik Pandya',
                'Jasprit Bumrah', 'Mohammed Shami', 'Rishabh Pant',
                'Kuldeep Yadav',
            ],
            'Nepal' => [
                'Paras Khadka', 'Rohit Paudel', 'Karan KC', 'Sandeep Lamichhane', 'Sompal Kami',
                'Dipendra Singh Airee', 'Basant Regmi', 'Gyanendra Malla', 'Aarif Sheikh',
                'Binod Bhandari', 'KC Bhurtel',
            ],
            'New Zealand' => [
                'Kane Williamson', 'Ross Taylor', 'Martin Guptill', 'James Neesham', 'Daryl Mitchell',
                'Mitchell Santner', 'Tom Latham', 'Trent Boult', 'Tim Southee', 'Lockie Ferguson',
                'Glenn Phillips',
            ],
            'Australia' => [
                'David Warner', 'Steve Smith', 'Marnus Labuschagne', 'Glenn Maxwell', 'Alex Carey',
                'Marcus Stoinis', 'Mitchell Marsh', 'Pat Cummins', 'Josh Hazlewood', 'Mitchell Starc',
                'Adam Zampa',
            ],
        ];

        $names = $namePoolByTeam[$teamName] ?? array_map(fn ($i) => $teamName . ' - Player ' . ($i + 1), range(0, 10));

        for ($i = 0; $i < 11; $i++) {
            $playerName = $names[$i];


            Player::firstOrCreate(
                [
                    'team_id' => $teamId,
                    'name' => $playerName,
                ],
                [
                    'role' => $roles[$i],
                    'credit' => $credits[$i] ?? 10.0,
                    'is_active' => true,
                    'batting_style' => null,
                    'bowling_style' => null,
                ]
            );
        }
    }
}

