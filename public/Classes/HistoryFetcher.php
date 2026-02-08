<?php

namespace FPL;

class HistoryFetcher
{
    private string $baseUrl = 'https://fantasy.premierleague.com/api/';
    private MultiCurlDownloader $downloader;
    private string $historyDir;
    private string $allHistFile;

    public function __construct(string $historyDir, string $allHistFile, int $cacheTime = 3600)
    {
        $this->historyDir = $historyDir;
        $this->allHistFile = $allHistFile;
        $this->downloader = new MultiCurlDownloader(50, $cacheTime);
    }

    public function fetchAllHistories(array $players): void
    {
        $urlsAndFiles = [];
        
        foreach ($players as $player) {
            $id = $player['id'];
            $url = $this->baseUrl . "element-summary/$id/";
            $filePath = $this->historyDir . "$id.json";
            $urlsAndFiles[$id] = ['url' => $url, 'filePath' => $filePath];
        }

        echo "Hole Player-Histories...\n";
        $results = $this->downloader->download($urlsAndFiles);

        echo "Aggregiere Histories in eine Datei...\n";
        $this->aggregateHistories($results);
    }

    private function aggregateHistories(array $results): void
    {
        $allHistories = [];
        
        foreach ($results as $id => $jsonString) {
            if ($jsonString !== false) {
                $histData = json_decode($jsonString, true);
                $allHistories[$id] = $histData['history'] ?? [];
            }
        }

        if (file_put_contents($this->allHistFile, json_encode($allHistories)) !== false) {
            echo "Alle Histories erfolgreich in $this->allHistFile gespeichert.\n";
        } else {
            throw new \RuntimeException("Fehler beim Speichern von $this->allHistFile");
        }
    }
}