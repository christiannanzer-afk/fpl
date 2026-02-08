<?php

namespace FPL;

class TeamBuilderDataLoader
{
    private TeamBuilderConfig $config;
    private array $players = [];
    private array $teams = [];
    private array $teamShortNames = [];
    
    public function __construct(TeamBuilderConfig $config)
    {
        $this->config = $config;
    }
    
    public function loadData(): void
    {
        $bootstrapFile = $this->config->getBootstrapFile();
        
        if (!file_exists($bootstrapFile)) {
            throw new \Exception('bootstrap-static.json fehlt!');
        }
        
        $data = json_decode(file_get_contents($bootstrapFile), true);
        $this->players = $data['elements'] ?? [];
        $this->teams = $data['teams'] ?? [];
        
        // Team-Kurznamen erstellen
        foreach ($this->teams as $team) {
            $code = $team['short_name'] ?? substr($team['name'], 0, 3);
            $this->teamShortNames[$team['id']] = strtoupper($code);
        }
        
        // Spieler nach total_points sortieren
        usort($this->players, fn($a, $b) => $b['total_points'] <=> $a['total_points']);
    }
    
    public function getPlayers(): array
    {
        return $this->players;
    }
    
    public function getTeams(): array
    {
        return $this->teams;
    }
    
    public function getTeamShortNames(): array
    {
        return $this->teamShortNames;
    }
    
    public function getPlayerData(): array
    {
        $playerData = [];
        $posMap = $this->config->getPositionMap();
        
        foreach ($this->players as $player) {
            $pos = $posMap[$player['element_type']] ?? '???';
            $photo = "photos/{$player['code']}.png";
            $club = $this->teamShortNames[$player['team']] ?? '???';
            
            $playerData[$player['id']] = [
                'id' => $player['id'],
                'name' => $player['web_name'],
                'pos' => $pos,
                'club' => $club,
                'teamId' => $player['team'],
                'photo' => $photo,
                'cost' => ($player['now_cost'] ?? 0) / 10
            ];
        }
        
        return $playerData;
    }
}
