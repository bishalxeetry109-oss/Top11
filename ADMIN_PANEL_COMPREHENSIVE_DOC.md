# Admin Panel Comprehensive Documentation (Backend + DB + Frontend)

> Project: `d:/College_Project/Top11`

This document explains **each admin dashboard functionality**, the **frontend page(s)** that implement it, and the **DB tables/fields** it depends on.

---

## 0) App Architecture Overview

### Core Admin controllers
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/MatchesController.php`
- `app/Http/Controllers/Admin/PlayerController.php`
- `app/Http/Controllers/Admin/PostController.php`
- `app/Http/Controllers/Admin/ContestController.php`
- `app/Http/Controllers/Admin/AdminUserController.php`
- `app/Http/Controllers/Admin/ScoreController.php`

### Frontend admin layout
- `resources/views/admin/layout/app.blade.php`
  - Sidebar navigation and global toast handling.

### Web routes
- `routes/web.php`
  - `Route::prefix('admin')->middleware(['auth','admin'])->group(...)`

---

## 1) Sidebar / Navigation (What admins can access)

**File:** `resources/views/admin/layout/app.blade.php`

Menu items:
- Dashboard → `admin.dashboard`
- Users → `users.index`
- Matches → `matches.index`
- Players → `players.index`
- Social Feed → `posts.index`

Score UI is implemented under:
- `admin/scores/{match}` route (ScoreController@index)

---

## 2) Dashboard

### Route / Controller
- `GET /admin/dashboard` → `Admin\DashboardController@index`

### Frontend page
- `resources/views/admin/dashboard.blade.php`

### What it shows
`DashboardController@index()` computes:
- **User stats**
  - `total_users`: users where `role = user`
  - `active_users`: users where `role = user` and `status = true`
- **Match stats**
  - `total_matches`
  - `upcoming_matches` (`status = UPCOMING`)
  - `live_matches` (`status = LIVE`)
  - `completed_matches` (`status = DONE`)
- **Contest stats**
  - `total_contests`
  - `open_contests` (`status = open`)
- **Post stats**
  - `total_posts`
- Contest money stats
  - `total_revenue` = `Contest.sum(entry_fee)`
  - `total_prize_pool` = `Contest.sum(prize_pool)`

### DB tables involved
- `users`
- `matches`
- `contests`
- `posts`

---

## 3) Users Management

### Routes / Controller
- `Route::resource('users', AdminUserController::class)`

### Frontend pages
- `resources/views/admin/users/index.blade.php` (visible list UI)

### DB tables
- `users`

### Expected fields (typical)
- `role`, `status`, plus authentication fields.

---

## 4) Players Management (Admin)

### Routes / Controller
- `Route::resource('players', PlayerController::class)`

### Frontend pages
- `resources/views/admin/players/index.blade.php`

### DB tables
- `players`

---

## 5) Matches Management

### Routes / Controller
- `Route::resource('matches', MatchesController::class)`
- `POST /admin/matches/{match}/add-players` → `MatchesController@addPlayers`
- `PATCH /admin/matches/{id}/toss` → `MatchesController@saveToss`
- `GET /admin/matches/{match}/next` → `MatchesController@showNext`

### Frontend pages
- `resources/views/admin/matches/index.blade.php`
- `resources/views/admin/matches/add.blade.php`
- `resources/views/admin/matches/next.blade.php` (add players)
- `resources/views/admin/matches/edit.blade.php`

### DB tables involved (from migrations)
**Core match setup**
- `matches`
  - `team_a_id`, `team_b_id`
  - `toss_winner_id`, `toss_winner_batting_id`
  - `toss_decision`
  - `current_innings`, `overs_limit`
  - `status`, winner/result fields

**Match innings totals**
- `match_innings`
  - `innings_number` (1/2)
  - `batting_team_id`, `bowling_team_id`
  - `runs`, `wickets`, `overs`, `balls`, `is_completed`

**Match player lineup**
- `match_players`
  - `match_id`, `player_id`, `team_id`, `role`
  - `credit`, `batting_status` (yet_to_bat/in_strike/in_non_strike/out)
  - `is_bowling`, `batting_position`

### Match player addition
`MatchesController@addPlayers()` creates:
- `players` (master table via `firstOrCreate`)
- `match_players` rows for the match

### Toss saving
`MatchesController@saveToss()`:
- Validates:
  - `toss_winner` required (should be `teams.id`)
  - `toss_decision` in `bat,bowl`
- Sets:
  - `toss_winner_id`
  - `toss_winner_batting_id`
  - `toss_decision`
- Computes innings teams using:
  - `team_a_id` / `team_b_id`
- Creates/sets:
  - `innings1_batting_team`, `innings1_bowling_team`
  - `innings2_batting_team`, `innings2_bowling_team`
  - `current_innings = 1`
  - updates match status to `LIVE` when needed

> Important: Your admin Score UI uses `match_innings` + `innings_state` as the authoritative scorer.

---

## 6) Contests Management

### Routes / Controller
- `Route::resource('contests', ContestController::class)`

### Frontend pages
- `resources/views/admin/contests/index.blade.php`
- `resources/views/admin/contests/create.blade.php`
- `resources/views/admin/contests/edit.blade.php` (if present)
- `resources/views/admin/contests/leaderboard.blade.php`

### DB tables
- `contests`
- `contest_entries`
- `matches` (relation via `match_id`)

---

## 7) Posts / Social Feed (Admin)

### Routes / Controller
- `Route::resource('posts', PostController::class)`

### Frontend pages
- `resources/views/admin/posts/index.blade.php`
- `resources/views/admin/posts/create.blade.php`
- `resources/views/admin/posts/comments.blade.php`

### DB tables
- `posts`
- `post_comments`

---

## 8) Score Management (Critical)

Score UI is the most complex admin feature. It lets admins run an innings ball-by-ball.

### Route / Controller
- `GET /admin/scores/{match}` → `ScoreController@index`

### Frontend page
- `resources/views/admin/scores/index.blade.php`

### High-level UI sections (what admin can do)
1. **Hero Scoreboard**
   - shows score/wickets/overs
2. **Toss Settings**
   - selects toss winner + decision
   - saves via `MatchesController@saveToss`
3. **Innings tabs** (UI switches between innings 1 & 2 panels)
4. **Batting card**
   - set striker
   - set non-striker
   - swap strike
   - current batsmen cards
   - next batsman selection (yet to bat)
5. **Bowling card**
   - shows current bowler stats
   - bowler ball pad:
     - dot, 1 run, 4, 6
     - wide, no ball
     - wicket, catch out
     - runout striker/nonstriker
6. **All batsmen / all bowlers tables**
7. **Live updates**
   - subscribes to Echo channel `match.{match_id}`
   - listens to event: `.ball.updated`

### DB schema used by scoring (authoritative)
From migrations:

**Match-level setup**
- `matches`
  - toss fields and status

**Innings totals**
- `match_innings`:
  - runs, wickets, overs, balls per innings

**Pointers for current state**
- `innings_state`:
  - striker_id, non_striker_id, bowler_id per innings
  - last_ball_number

**Batsman stats**
- `batsman_scorecards`:
  - runs, balls, fours, sixes, is_out, how_out

**Bowler stats**
- `bowler_scorecards`:
  - overs, balls, runs_conceded, wickets, maidens, economy

**Ball history**
- `ball_by_ball`:
  - over, ball number
  - striker_id, non_striker_id, bowler_id
  - runs, is_wicket, wicket_type, type, commentary

### ScoreController responsibilities
`app/Http/Controllers/Admin/ScoreController.php`

#### A) index(matchId)
- loads match + players
- ensures innings rows exist (if missing, creates them using toss/teams)
- sets computed hero scoreboard fields from `match_innings`
- builds `inningsData`:
  - current batsmen from `innings_state`
  - stats from `batsman_scorecards`
  - available batsmen from yet-to-start criteria (scorecards balls == 0)
  - bowlers from `bowler_scorecards` + `innings_state->bowler_id`

#### B) setBowler
- sets `innings_state.bowler_id`

#### C) swapStrike
- swaps `innings_state.striker_id` and `innings_state.non_striker_id`

#### D) markOut
- placeholder wicket via `BallProcessingService` wicket flag

#### E) setBattingFirst
- placeholder sets `matches.batting_first_id` (depends on your schema)

#### F) ballEvent
- receives UI payload `{type, runs, innings}`
- resolves innings id by innings_number
- requires `innings_state` pointers to be set
- calls `BallProcessingService->process()` to update totals + scorecards
- returns JSON payload used by the frontend:
  - `score`, `wickets`, `striker`, `bowler`, `swap_strike`

### Important gap
`app/Services/Cricket/BallProcessingService.php` currently:
- updates `match_innings` runs/wickets/overs/balls
- updates `batsman_scorecards` and increments balls
- updates `bowler_scorecards`
- **BUT it does not implement** full ball_by_ball insert / wide-no-ball legality / runouts / strike-swap rules.

So “basic” flow works, but full cricket rules may require extending the service.

### Frontend required data contract
From Blade, the backend must provide:
- `match.team_a`, `match.team_b`
- `match.batting_first`
- `match.score`, `match.wickets`, `match.overs`
- `match.toss_winner_id`, `match.toss_decision`
- `inningsData`:
  - `battingTeam`, `bowlingTeam`
  - `currentBatsmen` with: `runs`, `balls_faced`, `strike_rate`, `fours`, `sixes`, `batting_status`
  - `batsmens` table with: `runs`, `balls_faced`, `fours`, `sixes`, `strike_rate`, `is_out`, `is_batting`
  - `bowlers` with: `overs_bowled`, `runs_conceded`, `wickets`, `economy`, `is_bowling`
  - `availableBatsmen`, `availableBowlers`

---

## 9) Non-API Approach Confirmation

All admin functionality is routed via:
- `routes/web.php`
- Blade renders server-side
- scoring uses `fetch()` calls returning JSON

So no separate API layer is used for admin panels.

---

## 10) Suggested “Extra Functionality” Enhancements (Optional)

To improve admin scoring reliability:
1. Add endpoint: `POST /admin/scores/{match}/ensure-state`
   - auto-create missing `match_innings` and `innings_state`
2. Extend `BallProcessingService` to:
   - insert records into `ball_by_ball`
   - handle wide/no-ball legality
   - implement strike swap after legal ball + odd runs
   - implement runouts (wicket credited, correct striker/non-striker out)
3. Add admin “Undo last ball” using `ball_by_ball` history.

---

## 11) Database Summary (All Admin Core Tables)

- `users` (admin auth + user status)
- `teams`
- `players`
- `matches`
- `match_players`
- `match_innings`
- `innings_state`
- `batsman_scorecards`
- `bowler_scorecards`
- `ball_by_ball`
- `contests`
- `contest_entries`
- `posts`
- `post_comments`

---

## Appendix: Important Files

Controllers:
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/MatchesController.php`
- `app/Http/Controllers/Admin/ScoreController.php`

Blade:
- `resources/views/admin/layout/app.blade.php`
- `resources/views/admin/scores/index.blade.php`

Models:
- `app/Models/Matches.php`
- `app/Models/MatchInnings.php`
- `app/Models/InningsState.php`
- `app/Models/BatsmanScorecard.php`
- `app/Models/BowlerScorecard.php`
- `app/Models/BallByBall.php`

