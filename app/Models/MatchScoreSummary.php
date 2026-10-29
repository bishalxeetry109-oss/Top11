<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchScoreSummary extends Model
{
    use HasFactory;

    protected $table = 'match_score_summary';

    protected $fillable = [
        'match_id',
        'innings_1_runs',
        'innings_1_wickets',
        'innings_1_overs',
        'innings_2_runs',
        'innings_2_wickets',
        'innings_2_overs',
    ];

    protected $casts = [
        'innings_1_overs' => 'decimal:1',
        'innings_2_overs' => 'decimal:1',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
}

