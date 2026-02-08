<?php

namespace FPL;

class FixtureProcessor
{
    private array $fixtures;
    private array $teamNames;
    private int $nextGW;

    public function __construct(array $fixtures, array $teamNames, int $nextGW)
    {
        $this->fixtures = $fixtures;
        $this->teamNames = $teamNames;
        $this->nextGW = $nextGW;
    }

    public function getRecentResults(int $teamId): array
    {
        $teamFixtures = [];
        foreach ($this->fixtures as $fixture) {
            if ($fixture['finished'] && ($fixture['team_h'] == $teamId || $fixture['team_a'] == $teamId)) {
                $teamFixtures[] = $fixture;
            }
        }

        usort($teamFixtures, function($a, $b) {
            return strtotime($b['kickoff_time']) - strtotime($a['kickoff_time']);
        });

        $last5 = array_slice($teamFixtures, 0, 5);
        $results = [];

        foreach ($last5 as $f) {
            $isHome = $f['team_h'] == $teamId;
            $myScore = $isHome ? $f['team_h_score'] : $f['team_a_score'];
            $oppScore = $isHome ? $f['team_a_score'] : $f['team_h_score'];
            $oppId = $isHome ? $f['team_a'] : $f['team_h'];
            $oppShort = $this->teamNames[$oppId] ?? 'UNK';
            $result = $myScore > $oppScore ? 'W' : ($myScore == $oppScore ? 'D' : 'L');
            $scoreStr = $myScore . ':' . $oppScore;
            $ha = $isHome ? 'H' : 'A';
            $tooltip = 'GW' . $f['event'] . ': ' . $oppShort . ' (' . $ha . ') ' . $scoreStr;

            $results[] = ['result' => $result, 'tooltip' => $tooltip];
        }

        return $results;
    }

    public function getNextFixtures(int $teamId, array $teamCodes, int $fixtureCount): array
    {
        $unfinishedFixtures = array_filter($this->fixtures, function($f) {
            return !$f['finished'] && $f['event'] >= $this->nextGW;
        });

        usort($unfinishedFixtures, function($a, $b) {
            if ($a['event'] != $b['event']) return $a['event'] - $b['event'];
            return strtotime($a['kickoff_time']) - strtotime($b['kickoff_time']);
        });

        $teamNext = [];
        $gwCount = 0;
        $currentEventGW = null;

        foreach ($unfinishedFixtures as $fixture) {
            if ($fixture['team_h'] == $teamId || $fixture['team_a'] == $teamId) {
                // Prüfe ob neue GW
                if ($currentEventGW !== $fixture['event']) {
                    $gwCount++;
                    $currentEventGW = $fixture['event'];
                    if ($fixtureCount !== 'all' && $gwCount > $fixtureCount) break;
                }

                $opponentId = ($fixture['team_h'] == $teamId) ? $fixture['team_a'] : $fixture['team_h'];
                $opponentShort = $this->teamNames[$opponentId] ?? 'Unknown (ID: ' . $opponentId . ')';
                $isHome = $fixture['team_h'] == $teamId;
                $difficulty = $isHome ? $fixture['team_h_difficulty'] : $fixture['team_a_difficulty'];

                $teamNext[] = [
                    'opponent_id' => $opponentId,
                    'opponent' => $opponentShort,
                    'isHome' => $isHome,
                    'difficulty' => $difficulty,
                    'opponent_code' => $teamCodes[$opponentId] ?? 0,
                    'gw' => $fixture['event']
                ];
            }
        }

        return $teamNext;
    }

    public function getMaxFixturesPerGW(array $allTeamFixtures): array
    {
        $maxFixturesPerGW = [];

        foreach ($allTeamFixtures as $teamFixtures) {
            $gwFixtures = [];
            foreach ($teamFixtures as $fixt) {
                $gw = $fixt['gw'];
                if (!isset($gwFixtures[$gw])) $gwFixtures[$gw] = 0;
                $gwFixtures[$gw]++;
            }

            foreach ($gwFixtures as $gw => $count) {
                if (!isset($maxFixturesPerGW[$gw]) || $count > $maxFixturesPerGW[$gw]) {
                    $maxFixturesPerGW[$gw] = $count;
                }
            }
        }

        return $maxFixturesPerGW;
    }
}