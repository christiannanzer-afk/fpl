<?php

namespace FPL;

class LeagueTableCalculator
{
    private array $teams;
    private array $fixtures;
    private array $leagueTable = [];
    private array $ranks = [];

    public function __construct(array $teams, array $fixtures)
    {
        $this->teams = $teams;
        $this->fixtures = $fixtures;
        $this->calculate();
    }

    private function calculate(): void
    {
        // Initialisiere Tabelle
        for ($teamId = 1; $teamId <= count($this->teams); $teamId++) {
            $this->leagueTable[$teamId] = [
                'team_id' => $teamId,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'gf' => 0,
                'ga' => 0,
                'gd' => 0,
                'points' => 0,
                'cleansheets' => 0,
            ];
        }

        // Berechne Ergebnisse
        foreach ($this->fixtures as $f) {
            if (!$f['finished']) continue;

            $h = $f['team_h'];
            $a = $f['team_a'];
            $hs = $f['team_h_score'];
            $as = $f['team_a_score'];

            $this->leagueTable[$h]['played']++;
            $this->leagueTable[$a]['played']++;

            $this->leagueTable[$h]['gf'] += $hs;
            $this->leagueTable[$h]['ga'] += $as;
            $this->leagueTable[$a]['gf'] += $as;
            $this->leagueTable[$a]['ga'] += $hs;

            $this->leagueTable[$h]['gd'] += $hs - $as;
            $this->leagueTable[$a]['gd'] += $as - $hs;

            if ($hs > $as) {
                $this->leagueTable[$h]['wins']++;
                $this->leagueTable[$a]['losses']++;
                $this->leagueTable[$h]['points'] += 3;
            } elseif ($hs < $as) {
                $this->leagueTable[$a]['wins']++;
                $this->leagueTable[$h]['losses']++;
                $this->leagueTable[$a]['points'] += 3;
            } else {
                $this->leagueTable[$h]['draws']++;
                $this->leagueTable[$a]['draws']++;
                $this->leagueTable[$h]['points'] += 1;
                $this->leagueTable[$a]['points'] += 1;
            }

            if ($as == 0) $this->leagueTable[$h]['cleansheets']++;
            if ($hs == 0) $this->leagueTable[$a]['cleansheets']++;
        }

        $this->calculateRanks();
    }

    private function calculateRanks(): void
    {
        $sorted = $this->leagueTable;
        usort($sorted, function($a, $b) {
            if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
            if ($a['gd'] != $b['gd']) return $b['gd'] - $a['gd'];
            return $b['gf'] - $a['gf'];
        });

        foreach ($sorted as $index => $stats) {
            $this->ranks[$stats['team_id']] = $this->ordinal($index + 1);
        }
    }

    private function ordinal(int $number): string
    {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        }
        return $number . $ends[$number % 10];
    }

    public function getTable(): array
    {
        return $this->leagueTable;
    }

    public function getRanks(): array
    {
        return $this->ranks;
    }

    public function getPointsMap(): array
    {
        $map = [];
        foreach ($this->leagueTable as $tid => $stats) {
            $map[$tid] = $stats['points'] ?? 0;
        }
        return $map;
    }
}