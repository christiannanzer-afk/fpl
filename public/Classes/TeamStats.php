<?php

namespace FPL;

class TeamStats
{
    private array $teams;

    public function __construct(array $teams)
    {
        $this->teams = $teams;
    }

    public function getTeamWithStats(int $teamId, array $leagueTable, array $ranks): array
    {
        $team = null;
        foreach ($this->teams as $t) {
            if ($t['id'] === $teamId) {
                $team = $t;
                break;
            }
        }

        if (!$team) {
            return [];
        }

        return array_merge($team, [
            'stats' => $leagueTable[$teamId] ?? [],
            'rank' => $ranks[$teamId] ?? 'Unknown'
        ]);
    }
}