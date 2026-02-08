<?php

namespace FPL;

class PlayerSorter
{
    private string $sortBy;
    private string $sortDir;
    private array $teamNames;
    private array $positions;
    private bool $filterDreamTeam;
    private array $dreamTeam;

    public function __construct(
        string $sortBy,
        string $sortDir,
        array $teamNames,
        array $positions,
        bool $filterDreamTeam,
        array $dreamTeam
    ) {
        $this->sortBy = $sortBy;
        $this->sortDir = $sortDir;
        $this->teamNames = $teamNames;
        $this->positions = $positions;
        $this->filterDreamTeam = $filterDreamTeam;
        $this->dreamTeam = $dreamTeam;
    }

    public function sort(array &$players): void
    {
        usort($players, function($a, $b) {
            $valA = $this->getSortValue($a);
            $valB = $this->getSortValue($b);
            
            if ($valA == $valB) return 0;
            
            $cmp = ($valA < $valB) ? -1 : 1;
            return ($this->sortDir === 'asc') ? $cmp : -$cmp;
        });
    }

    private function getSortValue(array $player)
    {
        if ($this->filterDreamTeam && $this->sortBy === 'dream_order') {
            $index = array_search($player['id'], $this->dreamTeam);
            return $index !== false ? $index : 999;
        }

        switch ($this->sortBy) {
            case 'name':
                return strtolower($player['web_name']);
            case 'club':
                return strtolower($this->teamNames[$player['team']] ?? 'unknown');
            case 'position':
                return 5 - (int)$player['element_type'];
            case 'total_points':
                return (int)$player['total_points'];
            case 'points_home':
                return (int)($player['points_home'] ?? 0);
            case 'points_away':
                return (int)($player['points_away'] ?? 0);
            case 'points_per_game':
                return (float)$player['points_per_game'];
            case 'starts':
                return (int)$player['starts'];
            case 'minutes':
                return (int)$player['minutes'];
            case 'minutes_per_point':
                return $player['total_points'] > 0 
                    ? (float)$player['minutes'] / $player['total_points'] 
                    : 999999;
            case 'yellow_cards':
                return (int)$player['yellow_cards'];
            case 'yellow_cards_total':
                return (int)$player['yellow_cards'] + ((int)$player['red_cards'] * 2);
            case 'red_cards':
                return (int)$player['red_cards'];
            case 'form':
                return (float)$player['form'];
            case 'goals_scored':
                return (int)$player['goals_scored'];
            case 'assists':
                return (int)$player['assists'];
            case 'clean_sheets':
                return (int)$player['clean_sheets'];
            case 'selected_by_percent':
                return (float)$player['selected_by_percent'];
            case 'price':
                return (int)$player['now_cost'];
            case 'id':
                return (int)$player['id'];
            default:
                return (int)$player['total_points'];
        }
    }
}