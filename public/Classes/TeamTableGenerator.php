<?php

namespace FPL;

class TeamTableGenerator
{
    private Config $config;
    private array $params;

    public function __construct(Config $config, array $params)
    {
        $this->config = $config;
        $this->params = $params;
    }

    public function generateThead(): string
    {
        $columns = [
            'club' => 'Club',
            'rank' => 'Rank',
            'played' => 'P',
            'wins' => 'W',
            'draws' => 'D',
            'losses' => 'L',
            'gf' => 'GF',
            'ga' => 'GA',
            'gd' => 'GD',
            'cleansheets' => 'CS',
            'points' => 'Pts'
        ];

        $endAligned = ['picks', 'rank', 'played', 'wins', 'draws', 'losses', 'gf', 'ga', 'gd', 'points', 'cleansheets'];
        $thead = '<thead class="table-light"><tr>';

        foreach ($columns as $col => $label) {
            $class = 'sortable';
            if (in_array($col, $endAligned)) {
                $class .= ' text-end';
            }

            $nextDir = ($this->params['sortBy'] === $col && $this->params['sortDir'] === 'desc') ? 'asc' : 'desc';
            $sortIcon = '';
            if ($this->params['sortBy'] === $col) {
                $sortIcon = ($this->params['sortDir'] === 'asc') ? '↑' : '↓';
            }

            $thead .= sprintf(
                '<th class="%s" hx-get="%s" hx-target="#content" hx-swap="outerHTML" hx-trigger="click">%s%s</th>',
                $class,
                $this->buildUrl($col, $nextDir),
                $label,
                $sortIcon
            );
        }

        // History columns
        $startGw = max(1, $this->params['currentGw'] - $this->params['histCount'] + 1);
        for ($i = 0; $i < $this->params['histCount']; $i++) {
            $gwNum = $startGw + $i;
            $class = ($i === 0) ? 'text-center border-left-bold' : 'text-center';
            $thead .= '<th class="' . $class . '">GW' . $gwNum . '</th>';
        }

        // Future fixtures
        for ($i = 1; $i <= $this->params['fixtureCount']; $i++) {
            if ($this->params['fixtureDetails']) {
                $thead .= '<th colspan="3" class="text-center border-left-bold">GW' . ($this->params['nextGW'] + $i - 1) . '</th>';
            } else {
                $thead .= '<th class="text-center border-left-bold">GW' . ($this->params['nextGW'] + $i - 1) . '</th>';
            }
        }

        $thead .= '</tr></thead>';
        return $thead;
    }

    public function generateTbody(
        array $teams,
        array $teamNames,
        array $teamCodes,
        array $leagueTable,
        array $ranks,
        array $pastFixturesMap,
        array $nextFixtures,
        array $recentResults,
        FixtureColorCalculator $colorCalc
    ): string {
        $tbody = '<tbody>';

        foreach ($teams as $team) {
            $tbody .= $this->generateTeamRow(
                $team,
                $teamNames,
                $teamCodes,
                $leagueTable,
                $ranks,
                $pastFixturesMap,
                $nextFixtures,
                $recentResults,
                $colorCalc
            );
        }

        $tbody .= '</tbody>';
        return $tbody;
    }

    private function generateTeamRow(
        array $team,
        array $teamNames,
        array $teamCodes,
        array $leagueTable,
        array $ranks,
        array $pastFixturesMap,
        array $nextFixtures,
        array $recentResults,
        FixtureColorCalculator $colorCalc
    ): string {
        $teamId = $team['id'];
        $stats = $leagueTable[$teamId] ?? [];
        $rank = $ranks[$teamId] ?? 'Unknown';
        
        $clubBadge = file_exists(__DIR__ . "/../badges/{$team['code']}.svg") 
            ? "badges/{$team['code']}.svg" 
            : "https://resources.premierleague.com/premierleague/badges/{$team['code']}.svg";

        $row = '<tr>';
        $row .= '<td style="min-width:178px!important;"><img src="' . $clubBadge . '" alt="' . htmlspecialchars($team['short_name']) . '" height="28"> <strong>' . htmlspecialchars($team['name']) . '</strong></td>';
        $row .= '<td class="text-end">' . $rank . '</td>';
        $row .= '<td class="text-end">' . ($stats['played'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['wins'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['draws'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['losses'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['gf'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['ga'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['gd'] ?? 0) . '</td>';
        $row .= '<td class="text-end">' . ($stats['cleansheets'] ?? 0) . '</td>';
        $row .= '<td class="text-end"><strong>' . ($stats['points'] ?? 0) . '</strong></td>';

        // History columns
        $row .= $this->generateHistoryColumns($teamId, $teamNames, $teamCodes, $pastFixturesMap);

        // Fixture columns
        $row .= $this->generateFixtureColumns($teamId, $teamNames, $teamCodes, $nextFixtures, $recentResults, $leagueTable, $ranks, $colorCalc);

        $row .= '</tr>';
        return $row;
    }

    private function generateHistoryColumns(int $teamId, array $teamNames, array $teamCodes, array $pastFixturesMap): string
    {
        $html = '';
        $startGw = max(1, $this->params['currentGw'] - $this->params['histCount'] + 1);

        for ($i = 0; $i < $this->params['histCount']; $i++) {
            $gwNum = $startGw + $i;
            $class = ($i === 0) ? 'border-left-bold text-center' : 'text-center';
            $fixturesInGw = $pastFixturesMap[$teamId][$gwNum] ?? [];
            
            $content = '';
            if (empty($fixturesInGw)) {
                $content = '-';
            } else {
                foreach ($fixturesInGw as $index => $f) {
                    $isHome = $f['team_h'] == $teamId;
                    $oppId = $isHome ? $f['team_a'] : $f['team_h'];
                    $oppShort = $teamNames[$oppId] ?? 'UNK';
                    
                    $oppBadge = file_exists(__DIR__ . "/../badges/{$teamCodes[$oppId]}.svg") 
                        ? "badges/{$teamCodes[$oppId]}.svg" 
                        : "https://resources.premierleague.com/premierleague/badges/{$teamCodes[$oppId]}.svg";
                    
                    $ha = $isHome ? 'H' : 'A';
                    $myScore = (int)($isHome ? $f['team_h_score'] : $f['team_a_score']);
                    $oppScore = (int)($isHome ? $f['team_a_score'] : $f['team_h_score']);
                    $scoreStr = $myScore . ':' . $oppScore;
                    $result = $myScore > $oppScore ? 'W' : ($myScore == $oppScore ? 'D' : 'L');
                    $badgeClass = $result == 'W' ? 'bg-success' : ($result == 'D' ? 'bg-secondary' : 'bg-danger');
                    
                    $oppHtml = '<img src="' . $oppBadge . '" height="28" alt="Opponent Badge" style="filter: grayscale(100%);"> ' . 
                               $oppShort . ' (' . $ha . ') ' . $scoreStr . 
                               ' <small class="badge rounded-pill ' . $badgeClass . '">' . $result . '</small>';
                    
                    $content .= $oppHtml;
                    if ($index < count($fixturesInGw) - 1) $content .= '<br>';
                }
            }
            
            $html .= '<td class="' . $class . '">' . $content . '</td>';
        }

        return $html;
    }

    private function generateFixtureColumns(
        int $teamId,
        array $teamNames,
        array $teamCodes,
        array $nextFixtures,
        array $recentResults,
        array $leagueTable,
        array $ranks,
        FixtureColorCalculator $colorCalc
    ): string {
        $html = '';
        $teamFixtures = $nextFixtures[$teamId] ?? [];
        $displayCount = min($this->params['fixtureCount'], count($teamFixtures));

        for ($i = 0; $i < $displayCount; $i++) {
            $fixture = $teamFixtures[$i];
            $oppCode = $fixture['opponent_code'];
            
            $oppBadge = file_exists(__DIR__ . "/../badges/{$oppCode}.svg") 
                ? "badges/{$oppCode}.svg" 
                : "https://resources.premierleague.com/premierleague/badges/{$oppCode}.svg";
            
            $ha = $fixture['isHome'] ? 'H' : 'A';
            $dfr = $fixture['difficulty'];

            $badgeClass = match(true) {
                $dfr <= 2 => 'bg-success',
                $dfr == 3 => 'bg-secondary',
                $dfr == 4 => 'bg-warning',
                $dfr == 5 => 'bg-danger',
                default => 'bg-secondary',
            };

            $oppHtml = '<img src="' . $oppBadge . '" height="28" alt="Opponent Badge"> ' . 
                       $fixture['opponent'] . ' (' . $ha . ') ' . 
                       '<small style="font-size:8px;" class="position-relative translate-middle badge rounded-pill ' . $badgeClass . '">' . $dfr . '</small>';

            $fixtureText = $fixture['opponent'] . ' (' . $ha . ')';
            $info = $colorCalc->getFixtureInfo($fixtureText, $teamId);
            $bgStyle = 'background-color: ' . $info['color'] . ';';
            $tooltipAttr = ' data-bs-toggle="tooltip" title="GW' . $fixture['gw'] . '"';

            if ($this->params['fixtureDetails']) {
                $oppResults = array_reverse($recentResults[$fixture['opponent_id']] ?? []);
                $last5Html = '';
                foreach ($oppResults as $res) {
                    $color = $res['result'] == 'W' ? 'text-success' : ($res['result'] == 'D' ? 'text-secondary' : 'text-danger');
                    $icon = $res['result'] == 'W' ? 'bi-check-circle-fill' : ($res['result'] == 'D' ? 'bi-dash-circle-fill' : 'bi-x-circle-fill');
                    $last5Html .= '<i class="bi ' . $icon . ' ' . $color . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($res['tooltip']) . '"></i> ';
                }

                $rankOpp = $ranks[$fixture['opponent_id']] ?? 'Unknown';
                $gfOpp = $leagueTable[$fixture['opponent_id']]['gf'] ?? 0;
                $gaOpp = $leagueTable[$fixture['opponent_id']]['ga'] ?? 0;
                $csOpp = $leagueTable[$fixture['opponent_id']]['cleansheets'] ?? 0;
                $statsHtml = '<span data-bs-toggle="tooltip" title="Rank">' . $rankOpp . '</span> <span data-bs-toggle="tooltip" title="G/GA/CS">' . $gfOpp . '/' . $gaOpp . '/' . $csOpp . '</span>';

                $html .= '<td style="width:128px; max-width:128px; ' . $bgStyle . '" class="border-left-bold"' . $tooltipAttr . '>' . $oppHtml . '</td>';
                $html .= '<td style="width:120px; max-width:120px">' . $last5Html . '</td>';
                $html .= '<td style="width:130px; max-width:130px">' . $statsHtml . '</td>';
            } else {
                $html .= '<td style="width:128px; max-width:128px; ' . $bgStyle . '" class="border-left-bold"' . $tooltipAttr . '>' . $oppHtml . '</td>';
            }
        }

        // Fill missing fixtures
        for ($i = $displayCount; $i < $this->params['fixtureCount']; $i++) {
            if ($this->params['fixtureDetails']) {
                $html .= '<td style="width:128px; max-width:128px;" class="border-left-bold">No fixture</td>
                          <td style="width:120px; max-width:120px"></td>
                          <td style="width:130px; max-width:130px"></td>';
            } else {
                $html .= '<td style="width:128px; max-width:128px;" class="border-left-bold">No fixture</td>';
            }
        }

        return $html;
    }

    private function buildUrl(string $sortBy, string $sortDir): string
    {
        return sprintf(
            '?sort_by=%s&amp;sort_dir=%s&amp;hist_count=%s&amp;fixture_count=%s&amp;fixture_details=%s',
            $sortBy,
            $sortDir,
            $this->params['histCountGet'],
            $this->params['fixtureCountGet'],
            $this->params['fixtureDetails'] ? '1' : '0'
        );
    }
}