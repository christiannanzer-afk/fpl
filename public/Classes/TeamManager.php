<?php

namespace FPL;

class TeamManager
{
    private TeamBuilderConfig $config;
    
    public function __construct(TeamBuilderConfig $config)
    {
        $this->config = $config;
    }
    
    public function loadTeam(int $gw): array
    {
        if ($gw < 1 || $gw > 38) {
            return ['success' => false, 'error' => 'Ungültiger GW'];
        }
        
        $file = $this->config->getDreamTeamsDir() . '/dream_team_GW' . $gw . '.json';
        
        if (file_exists($file)) {
            $ids = json_decode(file_get_contents($file), true);
            return ['success' => true, 'ids' => $ids ?: []];
        }
        
        return ['success' => true, 'ids' => []];
    }
    
    public function saveTeam(int $gw, array $ids): array
    {
        if ($gw < 1 || $gw > 38) {
            return ['success' => false, 'error' => 'Ungültiger GW'];
        }
        
        if (!is_array($ids) || count($ids) !== $this->config->getMaxSquadSize()) {
            return ['success' => false, 'error' => 'Ungültige IDs'];
        }
        
        $this->config->ensureDreamTeamsDir();
        $file = $this->config->getDreamTeamsDir() . '/dream_team_GW' . $gw . '.json';
        
        file_put_contents($file, json_encode($ids, JSON_PRETTY_PRINT));
        
        return ['success' => true];
    }
    
    public function handleAjaxRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (isset($_POST['load_team'])) {
            $gw = intval($_POST['gw']);
            echo json_encode($this->loadTeam($gw));
            exit;
        }
        
        if (isset($_POST['save_team'])) {
            $gw = intval($_POST['gw']);
            $ids = json_decode($_POST['ids'], true);
            echo json_encode($this->saveTeam($gw, $ids));
            exit;
        }
    }
}
