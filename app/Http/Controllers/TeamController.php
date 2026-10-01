<?php

namespace App\Http\Controllers;

use App\Models\UserNft;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class TeamController extends Controller
{
    protected const PER_PAGE = 10;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $team = $user->teamMembers();

        $levels = $team->pluck('team_level')->unique()->sort()->values();
        $level = $request->integer('level') ?: null;
        $filtered = $level ? $team->where('team_level', $level)->values() : $team;

        $page = LengthAwarePaginator::resolveCurrentPage();
        $members = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // "Active" follows the dashboard's Active Member badge: an NFT
        // package that is still running.
        $activeMembersCount = UserNft::whereIn('user_id', $team->pluck('id'))
            ->where('status', 'active')
            ->distinct()
            ->count('user_id');

        return view('team', [
            'members' => $members->onEachSide(1),
            'levels' => $levels,
            'level' => $level,
            'totalTeamCount' => $team->count(),
            'directReferralsCount' => $team->where('team_level', 1)->count(),
            'activeMembersCount' => $activeMembersCount,
            'newThisMonthCount' => $team->filter(fn ($member) => $member->created_at->isCurrentMonth())->count(),
        ]);
    }
}
