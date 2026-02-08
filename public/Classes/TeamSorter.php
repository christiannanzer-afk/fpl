<?php

namespace FPL;

class TeamSorter
{
    private string $sortBy;
    private string $sortDir;

    public function __construct(string $sortBy, string $sortDir)
    {
        $this->sortBy = $sortBy;
        $this->sortDir = $sortDir;
    }

    public function sort(array &$teams, array $teamNames, array $numericalRanks, array $leagueTable, array $teamPicks): void
    {
        usort($teams, function($a, $b) use ($teamNames, $numericalRanks, $leagueTable, $teamPicks) {
            $valA = $this->getSortValue($a, $teamNames, $numericalRanks, $leagueTable, $teamPicks);
            $valB = $this->getSortValue($b, $teamNames, $numericalRanks, $leagueTable, $teamPicks);
            
            if ($valA == $valB) return 0;
            
            $cmp = ($valA < $valB) ? -1 : 1;
            return ($this->sortDir === 'asc') ? $cmp : -$cmp;
        });
    }

    private function getSortValue(array $team, array $teamNames, array $numericalRanks, array $leagueTable, array $teamPicks)
    {
        $stats = $leagueTable[$team['id']] ?? [];
        
        switch ($this->sortBy) {
            case 'club':
                return strtolower($team['short_name']);
            case 'rank':
                return (int)($numericalRanks[$team['id']] ?? 999);
            case 'played':
                return (int)($stats['played'] ?? 0);
            case 'wins':
                return (int)($stats['wins'] ?? 0);
            case 'draws':
                return (int)($stats['draws'] ?? 0);
            case 'losses':
                return (int)($stats['losses'] ?? 0);
            case 'gf':
                return (int)($stats['gf'] ?? 0);
            case 'ga':
                return (int)($stats['ga'] ?? 0);
            case 'gd':
                return (int)($stats['gd'] ?? 0);
            case 'points':
                return (int)($stats['points'] ?? 0);
            case 'cleansheets':
                return (int)($stats['cleansheets'] ?? 0);
            default:
                return (int)($stats['points'] ?? 0);
        }
    }
}