<?php

namespace FPL;

class PlayerFilter
{
    private int $minPoints;
    private int $filterTeam;
    private int $filterPosition;
    private bool $filterWatchlist;
    private bool $filterDreamTeam;
    private string $filterName;
    private array $watchlist;
    private array $dreamTeam;

    public function __construct(
        int $minPoints = 10,
        int $filterTeam = 0,
        int $filterPosition = 0,
        bool $filterWatchlist = false,
        bool $filterDreamTeam = false,
        string $filterName = '',
        array $watchlist = [],
        array $dreamTeam = []
    ) {
        $this->minPoints = $minPoints;
        $this->filterTeam = $filterTeam;
        $this->filterPosition = $filterPosition;
        $this->filterWatchlist = $filterWatchlist;
        $this->filterDreamTeam = $filterDreamTeam;
        $this->filterName = strtolower(trim($filterName));
        $this->watchlist = $watchlist;
        $this->dreamTeam = $dreamTeam;
    }

    public function filter(array $players): array
    {
        return array_filter($players, function($player) {
            // Mindestpunkte
            if ($player['total_points'] < $this->minPoints) {
                return false;
            }

            // Team-Filter
            if ($this->filterTeam > 0 && $player['team'] != $this->filterTeam) {
                return false;
            }

            // Position-Filter
            if ($this->filterPosition > 0 && $player['element_type'] != $this->filterPosition) {
                return false;
            }

            // Listen-Filter (OR-Logik)
            $requiredLists = [];
            if ($this->filterWatchlist) $requiredLists[] = $this->watchlist;
            if ($this->filterDreamTeam) $requiredLists[] = $this->dreamTeam;

            if (!empty($requiredLists)) {
                $inAny = false;
                foreach ($requiredLists as $list) {
                    if (in_array($player['id'], $list)) {
                        $inAny = true;
                        break;
                    }
                }
                if (!$inAny) return false;
            }

            // Name-Filter
            if ($this->filterName) {
                $fullName = strtolower(
                    $player['first_name'] . ' ' . 
                    $player['second_name'] . ' ' . 
                    $player['web_name']
                );
                if (strpos($fullName, $this->filterName) === false) {
                    return false;
                }
            }

            return true;
        });
    }
}