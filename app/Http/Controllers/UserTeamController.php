<?php

namespace App\Http\Controllers;

use App\Models\UserTeam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UserTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userTeams = UserTeam::forUser($request->user()->id)
            ->ordered()
            ->get()
            ->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->team_name,
                    'displayName' => $team->team_display_name,
                    'href' => $team->team_href,
                    'type' => $team->team_type,
                    'sort_order' => $team->sort_order,
                ];
            });

        return response()->json($userTeams);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'team_name' => 'required|string|max:255',
            'team_display_name' => 'required|string|max:255',
            'team_href' => 'required|string|max:255',
            'team_type' => 'required|in:namespace,page',
        ]);

        // Get the highest sort order for this user
        $maxOrder = UserTeam::forUser($request->user()->id)->max('sort_order') ?? 0;

        UserTeam::create([
            'user_id' => $request->user()->id,
            'team_name' => $request->team_name,
            'team_display_name' => $request->team_display_name,
            'team_href' => $request->team_href,
            'team_type' => $request->team_type,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Team added successfully');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $userTeam = UserTeam::forUser($request->user()->id)->findOrFail($id);
        $userTeam->delete();

        return back()->with('success', 'Team removed successfully');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'teams' => 'required|array',
            'teams.*.id' => 'required|integer|exists:user_teams,id',
            'teams.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->teams as $teamData) {
            UserTeam::forUser($request->user()->id)
                ->where('id', $teamData['id'])
                ->update(['sort_order' => $teamData['sort_order']]);
        }

        return back()->with('success', 'Teams reordered successfully');
    }
}
