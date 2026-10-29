@extends('admin.layout.app')

@section('title', 'Add Players to Match')
@section('page-title', 'Add Players to Match')

@section('content')

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Add Players to Match</h2>
            <p class="text-gray-400">
                {{ $match->teamA->name }} vs {{ $match->teamB->name }}
            </p>
        </div>
        <a href="{{ route('matches.index') }}"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center gap-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Matches
        </a>
    </div>

    <!-- Match Info Card -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div
                    class="w-16 h-16 rounded-xl bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold">
                        {{ $match->teamA->name }} vs {{ $match->teamB->name }}
                    </h3>
                    <p class="text-gray-400">{{ $match->sport }} • {{ $match->venue }}</p>
                    <p class="text-gray-400 text-sm">{{ \Carbon\Carbon::parse($match->date)->format('M d, Y') }} at
                        {{ $match->match_time }}
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-gray-400 text-sm">Players Added</p>
                <p class="text-2xl font-bold text-cyan-400">{{ $match->players->count() }}</p>
            </div>
        </div>
    </div>

    <!-- Add Players Form -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Add Players</h3>

        <form action="{{ route('matches.addPlayers', $match->id) }}" method="POST">
            @csrf

            <div id="playersContainer">
                <!-- Player rows will be added here dynamically -->
            </div>

            <button type="button" onclick="addPlayerRow()"
                class="mt-4 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Player
            </button>

            <div class="mt-6 flex justify-end gap-4">
                <a href="{{ route('matches.index') }}"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    Skip for Now
                </a>
                <button type="submit"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center gap-2 transition-colors font-semibold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Save Players
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Players -->
    @if($match->players->count() > 0)
        <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold">Existing Players ({{ $match->players->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Player</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Team</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Credit</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Points</th>
                        </tr>
                    </thead>
                    @php
                    @endphp
                    <tbody class="divide-y divide-gray-700">
                        @foreach($match->players as $player)
                            <tr class="hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-xs font-bold">
                                            {{ substr(optional($player->player)->name ?? $player->name ?? 'NA', 0, 2) }}
                                        </div>
                                        <p class="font-medium">{{ optional($player->player)->name ?? $player->name ?? 'N/A' }}</p>

                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    @php
                                        $teamName = optional($player->team)->name ?? 'N/A';
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs bg-blue-500/20 text-blue-400">
                                        Team {{ $teamName }}
                                    </span>
                                </td>

                                <td class="px-6 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs 
                                                                            @if($player->role === 'WK') bg-purple-500/20 text-purple-400
                                                                            @elseif($player->role === 'BAT') bg-blue-500/20 text-blue-400
                                                                            @elseif($player->role === 'AR') bg-yellow-500/20 text-yellow-400
                                                                            @else bg-green-500/20 text-green-400
                                                                            @endif">
                                        {{ $player->role }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-cyan-400 font-semibold">
                                    <input class="w-full bg-gray-900/40 border border-gray-700 rounded px-2 py-1 text-cyan-300"
                                           type="number" step="0.5" min="8" max="15"
                                           value="{{ $player->credit }}"
                                           data-match-player-id="{{ $player->id }}"
                                           data-field="credit" />
                                </td>
                                <td class="px-6 py-3 text-green-400 font-semibold">{{ $player->points }}</td>
                                <td class="px-6 py-3">
                                    <select class="w-full bg-gray-900/40 border border-gray-700 rounded px-2 py-1 text-white"
                                            data-match-player-id="{{ $player->id }}" data-field="role">
                                        <option value="WK" {{ $player->role==='WK'?'selected':'' }}>WK</option>
                                        <option value="BAT" {{ $player->role==='BAT'?'selected':'' }}>BAT</option>
                                        <option value="AR" {{ $player->role==='AR'?'selected':'' }}>AR</option>
                                        <option value="BOWL" {{ $player->role==='BOWL'?'selected':'' }}>BOWL</option>
                                    </select>
                                </td>
                                <td class="px-6 py-3 flex gap-2">
                                    <button type="button" class="px-3 py-1 bg-yellow-500/20 text-yellow-200 border border-yellow-500/30 rounded"
                                            data-action="edit" data-match-player-id="{{ $player->id }}">
                                        Edit
                                    </button>
                                    <button type="button" class="px-3 py-1 bg-red-500/20 text-red-200 border border-red-500/30 rounded"
                                            data-action="delete" data-match-player-id="{{ $player->id }}">
                                        Delete
                                    </button>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection

@section('scripts')
    <script>
        let playerCount = 0;
        let teamA = @json($match->teamA->id);
        let teamB = @json($match->teamB->id);

        let teamAName = @json($match->teamA->name);
        let teamBName = @json($match->teamB->name);
        function addPlayerRow() {
            const container = document.getElementById('playersContainer');
            const row = document.createElement('div');
            row.className = 'grid grid-cols-5 gap-4 mb-4 p-4 bg-gray-800 rounded-lg';
            row.id = `playerRow${playerCount}`;

            row.innerHTML = `
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Player Name</label>
                                <input type="text" name="players[${playerCount}][name]" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500"
                                       placeholder="Enter player name (or select existing player)">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Team</label>
                                <select name="players[${playerCount}][team]" required
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
                                    <option value="${teamA}">${teamAName}</option>
                                    <option value="${teamB}">${teamBName}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Role</label>
                                <select name="players[${playerCount}][role]" required
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500">
                                    <option value="WK">Wicket Keeper</option>
                                    <option value="BAT">Batsman</option>
                                    <option value="AR">All Rounder</option>
                                    <option value="BOWL">Bowler</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Credit (8-15)</label>
                                <input type="number" name="players[${playerCount}][credit]" min="8" max="15" step="0.5" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500"
                                       placeholder="8-15">
                            </div>
                            <div class="flex items-end">
                                <button type="button" onclick="removePlayerRow(${playerCount})" 
                                        class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                    Remove
                                </button>
                            </div>
                        `;

            container.appendChild(row);
            playerCount++;
        }

        function removePlayerRow(id) {
            const row = document.getElementById(`playerRow${id}`);
            if (row) {
                row.remove();
            }
        }

        // Add initial player row
        addPlayerRow();

        // Edit/Delete existing match players (role/credit + remove row)
        async function patchMatchPlayer(matchPlayerId, field, value) {
            const url = `/admin/matches/players/${matchPlayerId}`;
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    [field]: value
                })
            });
            return res.json();
        }

        async function deleteMatchPlayer(matchPlayerId) {
            const url = `/admin/matches/players/${matchPlayerId}`;
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            return res.json();
        }

        document.querySelectorAll('[data-action="edit"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.matchPlayerId;
                const res = await fetch(`/admin/matches/players/${id}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (!data.success) {
                    alert(data.message || 'Failed to fetch player');
                    return;
                }

                // Populate the row inputs/selects with latest values
                const row = btn.closest('tr');
                const creditInput = row.querySelector('input[data-match-player-id="' + id + '"][data-field="credit"]');
                const roleSelect = row.querySelector('select[data-match-player-id="' + id + '"][data-field="role"]');

                if (creditInput) creditInput.value = data.matchPlayer.credit;
                if (roleSelect) roleSelect.value = data.matchPlayer.role;

                // Update immediately using the same PATCH endpoint
                const payload = {
                    role: roleSelect ? roleSelect.value : data.matchPlayer.role,
                    credit: creditInput ? creditInput.value : data.matchPlayer.credit,
                };

                const patchRes = await fetch(`/admin/matches/players/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const patchData = await patchRes.json();
                if (patchData.success) location.reload();
                else alert(patchData.message || 'Update failed');
            });
        });

        document.querySelectorAll('[data-action="delete"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.matchPlayerId;
                if (!confirm('Delete this player from match?')) return;
                const data = await deleteMatchPlayer(id);
                if (data.success) location.reload();
                else alert(data.message || 'Delete failed');
            });
        });
    </script>
@endsection
