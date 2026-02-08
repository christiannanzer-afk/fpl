<?php

namespace FPL;

class DataLoader
{
    private Config $config;
    private array $players = [];
    private array $teams = [];
    private array $events = [];
    private array $fixtures = [];
    private array $histories = [];
    private array $watchlist = [];
    private array $dreamTeam = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function loadAll(): void
    {
        $this->loadBootstrap();
        $this->loadFixtures();
        $this->loadHistories();
        $this->loadWatchlist();
    }

    private function loadBootstrap(): void
    {
        $file = $this->config->get('paths.bootstrap');
        if (!file_exists($file)) {
            throw new \RuntimeException("Bootstrap file not found: $file");
        }

        $data = json_decode(file_get_contents($file), true);
        $this->players = $data['elements'] ?? [];
        $this->teams = $data['teams'] ?? [];
        $this->events = $data['events'] ?? [];

        // Home/Away Punkte berechnen
        $this->calculateHomeAwayPoints();
    }

    private function calculateHomeAwayPoints(): void
    {
        foreach ($this->players as &$player) {
            $player['points_home'] = 0;
            $player['points_away'] = 0;
        }

        $historyFile = $this->config->get('paths.all_histories');
        if (!file_exists($historyFile)) {
            return;
        }

        $allHistories = json_decode(file_get_contents($historyFile), true);
        foreach ($this->players as &$player) {
            $id = $player['id'];
            if (!isset($allHistories[$id])) continue;

            foreach ($allHistories[$id] as $hist) {
                if ($hist['minutes'] > 0) {
                    if ($hist['was_home']) {
                        $player['points_home'] += $hist['total_points'];
                    } else {
                        $player['points_away'] += $hist['total_points'];
                    }
                }
            }
        }
    }

    private function loadFixtures(): void
    {
        $file = $this->config->get('paths.fixtures');
        if (!file_exists($file)) {
            throw new \RuntimeException("Fixtures file not found: $file");
        }

        $this->fixtures = json_decode(file_get_contents($file), true);
    }

    private function loadHistories(): void
    {
        $file = $this->config->get('paths.all_histories');
        if (!file_exists($file)) {
            return;
        }

        $this->histories = json_decode(file_get_contents($file), true);
    }

    private function loadWatchlist(): void
    {
        $file = $this->config->get('paths.watchlist');
        if (file_exists($file)) {
            $this->watchlist = require $file;
        }
    }

    public function loadDreamTeam(int $gw = 0): void
    {
        if ($gw >= 1 && $gw <= 38) {
            $file = $this->config->get('paths.dream_teams_dir') . "dream_team_GW{$gw}.json";
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                $this->dreamTeam = is_array($data) ? $data : [];
                return;
            }
        }

        // Fallback
        $file = $this->config->get('paths.dream_team');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->dreamTeam = is_array($data) ? $data : [];
        }
    }

    // Getters
    public function getPlayers(): array { return $this->players; }
    public function getTeams(): array { return $this->teams; }
    public function getEvents(): array { return $this->events; }
    public function getFixtures(): array { return $this->fixtures; }
    public function getHistories(): array { return $this->histories; }
    public function getWatchlist(): array { return $this->watchlist; }
    public function getDreamTeam(): array { return $this->dreamTeam; }
}