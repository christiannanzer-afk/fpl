<?php

namespace FPL;

class TbodyGenerator
{
    private Config $config;
    private array $params;
    private array $teamNames;
    private array $teamCodes;
    private array $nextFixtures;
    private array $recentResults;
    private array $leagueTable;
    private array $ranks;
    private array $histories;
    private array $watchlist;
    private array $dreamTeam;
    private array $pointsMap;
    private FixtureColorCalculator $colorCalc;

    public function __construct(
        Config $config,
        array $params,
        array $teamNames,
        array $teamCodes,
        array $nextFixtures,
        array $recentResults,
        array $leagueTable,
        array $ranks,
        array $histories,
        array $watchlist,
        array $dreamTeam,
        array $pointsMap,
        FixtureColorCalculator $colorCalc
    ) {
        $this->config = $config;
        $this->params = $params;
        $this->teamNames = $teamNames;
        $this->teamCodes = $teamCodes;
        $this->nextFixtures = $nextFixtures;
        $this->recentResults = $recentResults;
        $this->leagueTable = $leagueTable;
        $this->ranks = $ranks;
        $this->histories = $histories;
        $this->watchlist = $watchlist;
        $this->dreamTeam = $dreamTeam;
        $this->pointsMap = $pointsMap;
        $this->colorCalc = $colorCalc;
    }

    public function generate(array $filteredPlayers): string
    {
        $fixtureProcessor = new FixtureProcessor([], [], $this->params['nextGW']);
        $maxFixturesPerGW = $fixtureProcessor->getMaxFixturesPerGW($this->nextFixtures);

        $playerParts = [];
        $prevGroup = null;
        $showBorders = (
            $this->params['filterDreamTeam'] && 
            $this->params['sortBy'] === 'dream_order' && 
            $this->params['sortDir'] === 'asc'
        );

        foreach ($filteredPlayers as $player) {
            $row = $this->generatePlayerRow($player, $prevGroup, $showBorders, $maxFixturesPerGW);
            $playerParts[] = $row['html'];
            $prevGroup = $row['group'];
        }

        return '<tbody>' . implode('', $playerParts) . '</tbody>';
    }

    private function generatePlayerRow(array $player, $prevGroup, bool $showBorders, array $maxFixturesPerGW): array
    {
        $positions = $this->config->get('positions');
        $position = $positions[$player['element_type']] ?? 'Unknown';
        $club = $this->teamNames[$player['team']] ?? 'Unknown';
        $price = $this->formatPrice($player['now_cost']);
        $teamCode = $this->teamCodes[$player['team']] ?? 0;
        $displaySrc = "https://fantasy.premierleague.com/dist/img/shirts/standard/shirt_{$teamCode}-220.webp";

        // Injury Icon
        $injuryIcon = $this->generateInjuryIcon($player);

        // Row Class für Borders
        $rowClass = '';
        $group = null;
        if ($showBorders) {
            $type = $player['element_type'];
            if ($this->params['filterDreamTeam']) {
                $group = $type;
            }
            if ($group != $prevGroup && $prevGroup !== null) {
                $rowClass = 'border-top-dark';
            }
        }

        // Club Tooltip
        $teamId = $player['team'];
        $rank = $this->ranks[$teamId] ?? 'Unknown';
        $gf = $this->leagueTable[$teamId]['gf'] ?? 0;
        $ga = $this->leagueTable[$teamId]['ga'] ?? 0;
        $cs = $this->leagueTable[$teamId]['cleansheets'] ?? 0;
        $clubTooltip = $rank . ' ' . $gf . ' / ' . $ga . ' / ' . $cs;

        // Start Row
        $html = sprintf(
            '<tr %s>
                <td style="min-width:178px!important;"><img src="%s" alt="%s" width="28" style="margin-top:-3px;"> <strong>%s%s</strong></td>
                <td style="min-width:63px;" data-bs-toggle="tooltip" title="%s">%s</td>
                <td style="min-width:60px;">%s</td>
                <td style="min-width:54px;" class="text-end">%s</td>
                <td style="min-width:54px;" class="text-end" data-bs-toggle="tooltip" title="Form">%s</td>
                <td style="min-width:56px;" class="text-end" data-bs-toggle="tooltip" title="Points per game">%s</td>
                <td style="min-width:40px;" class="text-end"><strong>%d</strong></td>
                <td style="min-width:40px;" class="text-end">%d</td>
                <td style="min-width:40px;" class="text-end">%d</td>',
            $rowClass ? 'class="' . $rowClass . '"' : '',
            $displaySrc,
            htmlspecialchars($player['web_name']),
            htmlspecialchars($player['web_name']),
            $injuryIcon,
            $clubTooltip,
            htmlspecialchars($club),
            $position,
            $price,
            $player['form'] ?? 0,
            $player['points_per_game'] ?? 0,
            $player['total_points'],
            $player['points_home'] ?? 0,
            $player['points_away'] ?? 0
        );

        // Player Details
        if ($this->params['playerDetails']) {
            $html .= $this->generatePlayerDetails($player);
        }

        // History Cells
        $html .= $this->generateHistoryCells($player);

        // Fixture Cells
        $html .= $this->generateFixtureCells($player, $maxFixturesPerGW);

        $html .= '</tr>';

        return ['html' => $html, 'group' => $group];
    }

    private function generateInjuryIcon(array $player): string
    {
        if (isset($player['status']) && in_array($player['status'], ['i','d','u','s']) && !empty($player['news'])) {
            $tooltip = htmlspecialchars($player['news'], ENT_QUOTES, 'UTF-8');
            return '<span title="' . $tooltip . '" style="color: red; margin-left: 5px;" data-bs-toggle="tooltip">&#9888;</span>';
        }
        return '';
    }

    private function generatePlayerDetails(array $player): string
    {
        $minutes = $player['minutes'] ?? 0;
        $points = $player['total_points'] ?? 0;
        $mp = ($points > 0) ? round($minutes / $points, 1) : '-';

        return sprintf(
            '<td style="min-width:44px;" class="text-end" data-bs-toggle="tooltip" title="Games started">%d</td>
             <td style="min-width:54px;" class="text-end" data-bs-toggle="tooltip" title="Minutes played">%d</td>
             <td style="min-width:54px;" class="text-end" data-bs-toggle="tooltip" title="Minutes per Point">%s</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Yellow Cards">%d</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Total Yellow Cards">%d</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Red Cards">%d</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Goals">%d</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Assists">%d</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Clean Sheets">%d</td>
             <td style="min-width:54px;" class="text-end" data-bs-toggle="tooltip" title="Selected by %%">%s</td>
             <td style="min-width:40px;" class="text-end" data-bs-toggle="tooltip" title="Player ID">%d</td>',
            $player['starts'] ?? 0,
            $minutes,
            $mp,
            $player['yellow_cards'] ?? 0,
            ($player['yellow_cards'] ?? 0) + ($player['red_cards'] ?? 0) * 2,
            $player['red_cards'] ?? 0,
            $player['goals_scored'] ?? 0,
            $player['assists'] ?? 0,
            $player['clean_sheets'] ?? 0,
            $player['selected_by_percent'] ?? 0,
            $player['id']
        );
    }

    private function generateHistoryCells(array $player): string
    {
        $html = '';
        $playerHist = $this->histories[$player['id']] ?? [];
        
        $histMap = [];
        foreach ($playerHist as $h) {
            if ($h['round'] <= $this->params['currentGw']) {
                $histMap[$h['round']] = $h;
            }
        }

        $startGw = max(1, $this->params['currentGw'] - $this->params['histCount'] + 1);
        $lastHist = [];
        for ($gw = $startGw; $gw <= $this->params['currentGw']; $gw++) {
            $lastHist[] = $histMap[$gw] ?? null;
        }

        for ($i = 0; $i < $this->params['histCount']; $i++) {
            $class = ($i === 0) ? 'border-left-bold text-center' : 'text-center';
            $content = '';
            $bgStyle = '';

            if ($lastHist[$i] === null) {
                $content = '-';
            } else {
                $h = $lastHist[$i];
                $content = $this->formatHistoryContent($h, $player);

                if ($h['round'] == $this->params['histHighlight']) {
                    $oppShort = $this->teamNames[$h['opponent_team'] ?? 0] ?? 'UNK';
                    $ha = $h['was_home'] ? 'H' : 'A';
                    $fixtureText = $oppShort . ' (' . $ha . ')';
                    $info = $this->colorCalc->getFixtureInfo($fixtureText, $player['team']);
                    $bgStyle = 'style="background-color: ' . $info['color'] . ' !important;"';
                }
            }

            $html .= '<td class="' . $class . '" ' . $bgStyle . '>' . $content . '</td>';
        }

        return $html;
    }

    private function formatHistoryContent(array $h, array $player): string
    {
        $oppShort = $this->teamNames[$h['opponent_team'] ?? 0] ?? 'UNK';
        $ha = $h['was_home'] ? 'H' : 'A';
        $myScore = $h['was_home'] ? ($h['team_h_score'] ?? '?') : ($h['team_a_score'] ?? '?');
        $oppScore = $h['was_home'] ? ($h['team_a_score'] ?? '?') : ($h['team_h_score'] ?? '?');
        $tooltip = 'GW' . $h['round'] . ': ' . $oppShort . ' (' . $ha . ') ' . $myScore . ':' . $oppScore;

        $points = (int)($h['total_points'] ?? 0);
        $pointsHtml = '<span data-bs-toggle="tooltip" title="' . htmlspecialchars($tooltip) . '">' . $points . '</span>';

        if ($points === 0 && $h['minutes'] == 0) {
            return '-';
        }

        $goals = '';
        $assists = '';
        $cs = '';

        if ($h['goals_scored'] > 0) {
            $goals = str_repeat('<span data-bs-toggle="tooltip" title="Goal">●</span>', $h['goals_scored']);
        }
        if ($h['assists'] > 0) {
            $assists = str_repeat('<span data-bs-toggle="tooltip" title="Assist">○</span>', $h['assists']);
        }
        if ($h['clean_sheets'] && in_array($player['element_type'], [1, 2, 3])) {
            $cs = $player['element_type'] === 3 
                ? '<span data-bs-toggle="tooltip" title="Clean Sheet (Mittelfeld)">□</span>'
                : '<span data-bs-toggle="tooltip" title="Clean Sheet (GK/DEF)">■</span>';
        }

        $content = $pointsHtml;
        $extra = trim($goals . $assists . $cs);
        if ($extra !== '') {
            $content .= ' ' . $extra;
        }

        return $content;
    }

    private function generateFixtureCells(array $player, array $maxFixturesPerGW): string
    {
        $html = '';
        $teamFixtures = $this->nextFixtures[$player['team']] ?? [];

        // Gruppiere nach GW
        $fixturesByGW = [];
        foreach ($teamFixtures as $fixture) {
            $gw = $fixture['gw'];
            if (!isset($fixturesByGW[$gw])) {
                $fixturesByGW[$gw] = [];
            }
            $fixturesByGW[$gw][] = $fixture;
        }

        for ($gwOffset = 0; $gwOffset < $this->params['fixtureCount']; $gwOffset++) {
            $thisGW = $this->params['nextGW'] + $gwOffset;
            $maxFix = $maxFixturesPerGW[$thisGW] ?? 1;
            $gwFixtures = $fixturesByGW[$thisGW] ?? [];
            $numFixtures = count($gwFixtures);

            $cellsPerFixture = $this->params['fixtureDetails'] ? 3 : 1;
            $totalCellsForGW = $maxFix * $cellsPerFixture;

            if ($numFixtures === 0) {
                // BGW
                $html .= '<td colspan="' . $totalCellsForGW . '" class="border-left-bold text-center">-</td>';
            } elseif ($numFixtures < $maxFix) {
                // Weniger Fixtures - zeige mit colspan
                $html .= $this->generateSingleFixtureCell($gwFixtures[0], $player['team'], $totalCellsForGW, true);
            } else {
                // DGW - zeige alle nebeneinander
                foreach ($gwFixtures as $idx => $fixture) {
                    $isFirst = ($idx === 0);
                    $html .= $this->generateSingleFixtureCell($fixture, $player['team'], 1, $isFirst);
                }
            }
        }

        return $html;
    }

    private function generateSingleFixtureCell(array $fixture, int $teamId, int $colspan, bool $isFirst): string
    {
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
        $info = $this->colorCalc->getFixtureInfo($fixtureText, $teamId);
        $bgStyle = 'background-color: ' . $info['color'] . ';';

        $cellClass = $isFirst ? 'border-left-bold' : '';
        $tooltipAttr = ' data-bs-toggle="tooltip" title="GW' . $fixture['gw'] . '"';

        if ($this->params['fixtureDetails'] && $colspan > 1) {
            // Kombinierte Darstellung
            $oppResults = array_reverse($this->recentResults[$fixture['opponent_id']] ?? []);
            $last5Html = '';
            foreach ($oppResults as $res) {
                $color = $res['result'] == 'W' ? 'text-success' : ($res['result'] == 'D' ? 'text-secondary' : 'text-danger');
                $icon = $res['result'] == 'W' ? 'bi-check-circle-fill' : ($res['result'] == 'D' ? 'bi-dash-circle-fill' : 'bi-x-circle-fill');
                $last5Html .= '<i class="bi ' . $icon . ' ' . $color . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($res['tooltip']) . '"></i> ';
            }

            $rank = $this->ranks[$fixture['opponent_id']] ?? 'Unknown';
            $gf = $this->leagueTable[$fixture['opponent_id']]['gf'] ?? 0;
            $ga = $this->leagueTable[$fixture['opponent_id']]['ga'] ?? 0;
            $cs = $this->leagueTable[$fixture['opponent_id']]['cleansheets'] ?? 0;
            $statsHtml = '<span data-bs-toggle="tooltip" title="Rank">' . $rank . '</span> <span data-bs-toggle="tooltip" title="G/GA/CS">' . $gf . '/' . $ga . '/' . $cs . '</span>';

            $combinedHtml = $oppHtml . '<br><small>' . $last5Html . '</small><br><small>' . $statsHtml . '</small>';
            
            return '<td colspan="' . $colspan . '" style="width:128px; max-width:128px; ' . $bgStyle . '" class="' . $cellClass . '"' . $tooltipAttr . '>' . $combinedHtml . '</td>';
        } elseif ($this->params['fixtureDetails']) {
            // Separate Zellen
            $oppResults = array_reverse($this->recentResults[$fixture['opponent_id']] ?? []);
            $last5Html = '';
            foreach ($oppResults as $res) {
                $color = $res['result'] == 'W' ? 'text-success' : ($res['result'] == 'D' ? 'text-secondary' : 'text-danger');
                $icon = $res['result'] == 'W' ? 'bi-check-circle-fill' : ($res['result'] == 'D' ? 'bi-dash-circle-fill' : 'bi-x-circle-fill');
                $last5Html .= '<i class="bi ' . $icon . ' ' . $color . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($res['tooltip']) . '"></i> ';
            }

            $rank = $this->ranks[$fixture['opponent_id']] ?? 'Unknown';
            $gf = $this->leagueTable[$fixture['opponent_id']]['gf'] ?? 0;
            $ga = $this->leagueTable[$fixture['opponent_id']]['ga'] ?? 0;
            $cs = $this->leagueTable[$fixture['opponent_id']]['cleansheets'] ?? 0;
            $statsHtml = '<span data-bs-toggle="tooltip" title="Rank">' . $rank . '</span> <span data-bs-toggle="tooltip" title="G/GA/CS">' . $gf . '/' . $ga . '/' . $cs . '</span>';

            return '<td style="width:128px; max-width:128px; ' . $bgStyle . '" class="' . $cellClass . '"' . $tooltipAttr . '>' . $oppHtml . '</td>
                    <td style="width:120px; max-width:120px">' . $last5Html . '</td>
                    <td style="width:130px; max-width:130px">' . $statsHtml . '</td>';
        } else {
            // Nur Gegner
            return '<td colspan="' . $colspan . '" style="width:128px; max-width:128px; ' . $bgStyle . '" class="' . $cellClass . '"' . $tooltipAttr . '>' . $oppHtml . '</td>';
        }
    }

    private function formatPrice(int $price): string
    {
        return '£' . number_format($price / 10, 1);
    }
}