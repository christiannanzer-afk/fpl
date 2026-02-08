<?php

namespace FPL;

class TheadGenerator
{
    private Config $config;
    private array $params;

    public function __construct(Config $config, array $params)
    {
        $this->config = $config;
        $this->params = $params;
    }

    public function generate(array $nextFixtures): string
    {
        $columns = $this->config->get('columns');
        $endAligned = ['total_points','points_home','points_away','points_per_game','starts','minutes','minutes_per_point','yellow_cards','yellow_cards_total','red_cards','form','goals_scored','assists','clean_sheets','selected_by_percent','price','id'];
        
        $thead = '<thead class="table-light"><tr>';

        // Spalten-Header
        foreach ($columns as $col => $label) {
            if (!$this->params['playerDetails'] && in_array($col, ['starts','minutes','minutes_per_point','yellow_cards','yellow_cards_total','red_cards','goals_scored','assists','clean_sheets','selected_by_percent','id'])) {
                continue;
            }

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

        // History-Kolonnen
        $startGw = max(1, $this->params['currentGw'] - $this->params['histCount'] + 1);
        for ($i = 0; $i < $this->params['histCount']; $i++) {
            $gwNum = $startGw + $i;
            $class = ($i === 0) ? 'text-center border-left-bold clickable' : 'text-center clickable';
            
            $thead .= sprintf(
                '<th class="%s" hx-get="%s" hx-target="#content" hx-swap="outerHTML" hx-trigger="click">GW%s</th>',
                $class,
                $this->buildUrlForGW($gwNum),
                $gwNum
            );
        }

        // Fixture-Kolonnen mit DGW-Support
        $fixtureProcessor = new FixtureProcessor([], [], $this->params['nextGW']);
        $maxFixturesPerGW = $fixtureProcessor->getMaxFixturesPerGW($nextFixtures);

        for ($gwOffset = 0; $gwOffset < $this->params['fixtureCount']; $gwOffset++) {
            $thisGW = $this->params['nextGW'] + $gwOffset;
            $maxFix = $maxFixturesPerGW[$thisGW] ?? 1;

            $colspan = $this->params['fixtureDetails'] ? ($maxFix * 3) : $maxFix;

            $thead .= sprintf(
                '<th colspan="%d" class="text-center border-left-bold clickable" hx-get="%s" hx-target="#content" hx-swap="outerHTML" hx-trigger="click">GW%s</th>',
                $colspan,
                $this->buildUrlForGW($thisGW, false),
                $thisGW
            );
        }

        $thead .= '</tr></thead>';
        return $thead;
    }

    private function buildUrl(string $sortBy, string $sortDir): string
    {
        return sprintf(
            '?min_points=%d&amp;limit=%d&amp;sort_by=%s&amp;sort_dir=%s&amp;hist_count=%s&amp;fixture_count=%s&amp;filter_team=%d&amp;filter_position=%d&amp;filter_watchlist=%s&amp;filter_dreamteam=%s&amp;filter_name=%s&amp;gw_dream=%d&amp;fixture_details=%s&amp;player_details=%s&amp;hist_highlight=%d',
            $this->params['minPoints'],
            $this->params['limit'],
            $sortBy,
            $sortDir,
            $this->params['histCountGet'],
            $this->params['fixtureCountGet'],
            $this->params['filterTeam'],
            $this->params['filterPosition'],
            $this->params['filterWatchlistStr'],
            $this->params['filterDreamTeamStr'],
            htmlspecialchars($this->params['filterName']),
            $this->params['gwDream'],
            $this->params['fixtureDetails'] ? '1' : '0',
            $this->params['playerDetails'] ? '1' : '0',
            $this->params['histHighlight']
        );
    }

    private function buildUrlForGW(int $gwNum, bool $isHistory = true): string
    {
        $params = $this->params;
        
        if ($isHistory) {
            $params['histHighlight'] = $gwNum;
            $params['filterDreamTeamStr'] = '1';
            $params['gwDream'] = $gwNum;
        } else {
            $params['filterDreamTeamStr'] = '1';
            $params['gwDream'] = $gwNum;
        }

        return sprintf(
            '?min_points=%d&amp;limit=%d&amp;sort_by=%s&amp;sort_dir=%s&amp;hist_count=%s&amp;fixture_count=%s&amp;filter_team=%d&amp;filter_position=%d&amp;filter_watchlist=%s&amp;filter_dreamteam=%s&amp;filter_name=%s&amp;gw_dream=%d&amp;fixture_details=%s&amp;player_details=%s&amp;hist_highlight=%d',
            $params['minPoints'],
            $params['limit'],
            $params['sortBy'],
            $params['sortDir'],
            $params['histCountGet'],
            $params['fixtureCountGet'],
            $params['filterTeam'],
            $params['filterPosition'],
            $params['filterWatchlistStr'],
            $params['filterDreamTeamStr'],
            htmlspecialchars($params['filterName']),
            $params['gwDream'],
            $params['fixtureDetails'] ? '1' : '0',
            $params['playerDetails'] ? '1' : '0',
            $params['histHighlight']
        );
    }
}