<?php

namespace FPL;

class TeamFixtureProcessor
{
    private array $fixtures;
    private array $teamNames;
    private array $teamCodes;

    public function __construct(array $fixtures, array $teamNames, array $teamCodes)
    {
        $this->fixtures = $fixtures;
        $this->teamNames = $teamNames;
        $this->teamCodes = $teamCodes;
    }

    public function getPastFixturesByGW(int $teamId, int $currentGw): array
    {
        $finishedFixtures = array_filter($this->fixtures, fn($f) => $f['finished']);
        usort($finishedFixtures, fn($a, $b) => $a['event'] <=> $b['event']);

        $pastFixturesMap = [];
        foreach ($finishedFixtures as $f) {
            if ($f['team_h'] == $teamId || $f['team_a'] == $teamId) {
                $gw = $f['event'];
                if (!isset($pastFixturesMap[$gw])) {
                    $pastFixturesMap[$gw] = [];
                }
                $pastFixturesMap[$gw][] = $f;
            }
        }

        return $pastFixturesMap;
    }

    public function getUpcomingFixtures(int $teamId, int $nextGW, int $fixtureCount): array
    {
        $unfinishedFixtures = array_filter($this->fixtures, function($f) use ($nextGW) {
            return !$f['finished'] && $f['event'] >= $nextGW;
        });

        usort($unfinishedFixtures, fn($a, $b) => strtotime($a['kickoff_time']) - strtotime($b['kickoff_time']));

        $teamNext = [];
        $count = 0;

        foreach ($unfinishedFixtures as $fixture) {
            if ($fixture['team_h'] == $teamId || $fixture['team_a'] == $teamId) {
                $opponentId = ($fixture['team_h'] == $teamId) ? $fixture['team_a'] : $fixture['team_h'];
                $isHome = $fixture['team_h'] == $teamId;

                $teamNext[] = [
                    'opponent_id' => $opponentId,
                    'opponent' => $this->teamNames[$opponentId] ?? 'Unknown',
                    'isHome' => $isHome,
                    'difficulty' => $isHome ? $fixture['team_h_difficulty'] : $fixture['team_a_difficulty'],
                    'opponent_code' => $this->teamCodes[$opponentId] ?? 0,
                    'gw' => $fixture['event']
                ];

                $count++;
                if ($fixtureCount !== 'all' && $count >= $fixtureCount) break;
            }
        }

        return $teamNext;
    }

    public function getAllPastFixturesMap(int $currentGw): array
    {
        $finishedFixtures = array_filter($this->fixtures, fn($f) => $f['finished']);
        usort($finishedFixtures, fn($a, $b) => $a['event'] <=> $b['event']);

        $pastFixturesMap = [];
        
        foreach ($finishedFixtures as $f) {
            $homeId = $f['team_h'];
            $awayId = $f['team_a'];
            $gw = $f['event'];

            foreach ([$homeId, $awayId] as $teamId) {
                if (!isset($pastFixturesMap[$teamId])) {
                    $pastFixturesMap[$teamId] = [];
                }
                if (!isset($pastFixturesMap[$teamId][$gw])) {
                    $pastFixturesMap[$teamId][$gw] = [];
                }
                $pastFixturesMap[$teamId][$gw][] = $f;
            }
        }

        return $pastFixturesMap;
    }

    public function getAllUpcomingFixtures(int $nextGW, int $fixtureCount): array
    {
        $nextFixtures = [];
        $teamCount = max(array_column($this->fixtures, 'team_h'));

        for ($teamId = 1; $teamId <= $teamCount; $teamId++) {
            $nextFixtures[$teamId] = $this->getUpcomingFixtures($teamId, $nextGW, $fixtureCount);
        }

        return $nextFixtures;
    }
}