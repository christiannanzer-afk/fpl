<?php

namespace FPL;

class AssetDownloader
{
    private MultiCurlDownloader $downloader;

    public function __construct()
    {
        // Permanent cache for assets (cacheTime = 0)
        $this->downloader = new MultiCurlDownloader(50, 0);
    }

    public function downloadPlayerPhotos(array $players, array $teamCodes, string $photoDir): void
    {
        echo "Hole Player-Photos...\n";
        
        $urlsAndFiles = [];
        foreach ($players as $player) {
            $code = $player['code'];
            $filePath = $photoDir . "$code.png";
            $url = "https://resources.premierleague.com/premierleague/photos/players/110x140/p$code.png";
            $urlsAndFiles[$code] = ['url' => $url, 'filePath' => $filePath];
        }

        $results = $this->downloader->download($urlsAndFiles, true);

        // Fallback für Fehlschläge
        $fallbackNeeded = [];
        foreach ($results as $code => $result) {
            if ($result === false) {
                $playerIndex = array_search($code, array_column($players, 'code'));
                if ($playerIndex !== false) {
                    $teamId = $players[$playerIndex]['team'] ?? 0;
                    $teamCode = $teamCodes[$teamId] ?? 0;
                    $fallbackUrl = "https://fantasy.premierleague.com/dist/img/shirts/standard/shirt_$teamCode-220.webp";
                    $filePath = $photoDir . "$code.png";
                    $fallbackNeeded[$code] = ['url' => $fallbackUrl, 'filePath' => $filePath];
                }
            }
        }

        if (!empty($fallbackNeeded)) {
            echo "Verarbeite Fallback-Photos...\n";
            $this->downloader->download($fallbackNeeded, true);
        }

        echo "Alle Player-Photos wurden verarbeitet.\n";
    }

    public function downloadTeamBadges(array $teams, string $badgeDir): void
    {
        echo "Hole Team-Badges...\n";
        
        $urlsAndFiles = [];
        foreach ($teams as $team) {
            $code = $team['code'];
            $filePath = $badgeDir . "$code.svg";
            $url = "https://resources.premierleague.com/premierleague/badges/$code.svg";
            $urlsAndFiles[$code] = ['url' => $url, 'filePath' => $filePath];
        }

        $this->downloader->download($urlsAndFiles, true);
        echo "Alle Team-Badges wurden verarbeitet.\n";
    }
}