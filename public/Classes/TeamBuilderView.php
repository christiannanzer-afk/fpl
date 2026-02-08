<?php

namespace FPL;

class TeamBuilderView
{
    private TeamBuilderConfig $config;
    private TeamBuilderDataLoader $dataLoader;
    
    public function __construct(TeamBuilderConfig $config, TeamBuilderDataLoader $dataLoader)
    {
        $this->config = $config;
        $this->dataLoader = $dataLoader;
    }
    
    public function renderHead(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FPL Team Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/team_builder.css" rel="stylesheet">
</head>
HTML;
    }
    
    public function renderPlayerList(): string
    {
        $players = $this->dataLoader->getPlayers();
        $posMap = $this->config->getPositionMap();
        $teamShort = $this->dataLoader->getTeamShortNames();
        
        $html = '<div id="list" class="player-list">';
        
        foreach ($players as $player) {
            $pos = $posMap[$player['element_type']] ?? '???';
            $photo = "photos/{$player['code']}.png";
            $club = $teamShort[$player['team']] ?? '???';
            $cost = ($player['now_cost'] ?? 0) / 10;
            $name = htmlspecialchars($player['web_name']);
            
            $html .= <<<HTML
                        <div class="player-item" draggable="true"
                             data-id="{$player['id']}"
                             data-name="{$name}"
                             data-club="{$club}"
                             data-pos="{$pos}"
                             data-team-id="{$player['team']}"
                             data-photo="{$photo}"
                             data-cost="{$cost}">
                            <img src="{$photo}" alt="" draggable="false">
                            <div>
                                <div class="name">{$player['web_name']}</div>
                                <div class="pts">{$player['total_points']} Pkt | {$club}</div>
                            </div>
                            <div class="pos">{$pos}</div>
                            <div class="cost">{$cost}</div>
                        </div>
HTML;
        }
        
        $html .= '</div>';
        return $html;
    }
    
    public function renderSquadBuilder(): string
    {
        return <<<HTML
            <div class="card shadow">
                <div class="card-body">
                    <select id="gw-select" class="form-select">
                        <option value="0">Neues Team</option>
HTML
        . $this->renderGameweekOptions() .
        <<<HTML
                    </select>
                    <div id="value-display">
                        <span class="value-total">0.0/100.0</span>
                        <span class="value-diff" class="value-diff"></span>
                    </div>
                    <div class="counter mb-4" id="status">Zieh Spieler hierher</div>

                    <div class="row h-100">
                        <!-- LINKS: Startelf -->
                        <div class="col-10 pe-3">
                            <div class="zone-wrapper"><div class="zone-label">GK (0/1)</div>
                                <div id="zone-gk" class="drop-zone"></div></div>

                            <div class="zone-wrapper"><div class="zone-label">DEF (0/5)</div>
                                <div id="zone-def" class="drop-zone"></div></div>

                            <div class="zone-wrapper"><div class="zone-label">MID (0/5)</div>
                                <div id="zone-mid" class="drop-zone"></div></div>

                            <div class="zone-wrapper"><div class="zone-label">FWD (0/3)</div>
                                <div id="zone-fwd" class="drop-zone"></div></div>
                        </div>

                        <!-- RECHTS: Bank -->
                        <div class="col-2 ps-3">
                            <div class="d-flex flex-column h-100">
                                <div class="zone-wrapper mb-3"><div class="zone-label">GK (0/1)</div>
                                    <div id="zone-bench-gk" class="drop-zone bench-zone"></div></div>

                                <div class="zone-wrapper flex-grow-1"><div class="zone-label">Feld (0/3)</div>
                                    <div id="zone-bench-field" class="drop-zone bench-zone"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
HTML;
    }
    
    private function renderGameweekOptions(): string
    {
        $html = '';
        for ($i = 1; $i <= 38; $i++) {
            $selected = $i === $this->config->getDefaultGw() ? ' selected' : '';
            $html .= "<option value=\"{$i}\"{$selected}>GW {$i}</option>\n";
        }
        return $html;
    }
    
    public function renderPlayerData(): string
    {
        $playerData = $this->dataLoader->getPlayerData();
        return 'const allPlayers = ' . json_encode($playerData) . ';';
    }
    
    public function renderScripts(): string
    {
        $defaultGw = $this->config->getDefaultGw();
        
        return <<<HTML
    <script>
        const defaultGw = {$defaultGw};
        {$this->renderPlayerData()}
    </script>
    <script src="assets/js/team_builder.js"></script>
HTML;
    }
}
