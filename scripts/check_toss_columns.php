<?php

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Schema;

// Load Laravel bootstrap
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(
    Illuminate\Contracts\Http\Kernel::class
);

$kernel->bootstrap();

// Use Laravel container to resolve Schema facade bindings
// (Schema facade works after bootstrapping)

$checks = [
    'toss_decision',
    'toss_winner_id',
    'toss_winner_batting_id',
    'innings1_batting_team',
    'innings1_bowling_team',
    'innings2_batting_team',
    'innings2_bowling_team',
    'batting_team',
    'bowling_team',
];

foreach ($checks as $col) {
    $exists = Schema::hasColumn('matches', $col);
    echo $col . '=' . ($exists ? 'YES' : 'NO') . PHP_EOL;
}

