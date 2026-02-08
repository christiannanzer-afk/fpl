<?php

namespace FPL;

class ApiClient
{
    private string $baseUrl = 'https://fantasy.premierleague.com/api/';
    private ?string $accessToken;
    private int $cacheTime;

    public function __construct(?string $accessToken = null, int $cacheTime = 3600)
    {
        $this->accessToken = $accessToken;
        $this->cacheTime = $cacheTime;
    }

    public function fetchBootstrap(string $cacheFile): array
    {
        $url = $this->baseUrl . 'bootstrap-static/';
        $json = $this->fetchAndCache($url, $cacheFile);
        return json_decode($json, true) ?? [];
    }

    public function fetchFixtures(string $cacheFile): array
    {
        $url = $this->baseUrl . 'fixtures/';
        $json = $this->fetchAndCache($url, $cacheFile);
        return json_decode($json, true) ?? [];
    }

    public function fetchMyTeam(int $teamId, string $cacheFile): array
    {
        if (!$this->accessToken) {
            throw new \RuntimeException('Access token required for my-team endpoint');
        }

        $url = $this->baseUrl . "my-team/{$teamId}/";
        
        if ($this->isCacheValid($cacheFile)) {
            echo "Lade my-team aus Cache: $cacheFile\n";
            return json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Referer: https://fantasy.premierleague.com/',
            'Origin: https://fantasy.premierleague.com'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("Fehler beim Holen von my-team (HTTP $httpCode)");
        }

        curl_close($ch);

        $this->saveToCache($cacheFile, $response);
        echo "my-team-Daten geholt und gespeichert: $cacheFile\n";

        return json_decode($response, true) ?? [];
    }

    private function fetchAndCache(string $url, string $cacheFile): string
    {
        if ($this->isCacheValid($cacheFile)) {
            echo "Lade aus Cache: $cacheFile\n";
            return file_get_contents($cacheFile);
        }

        $data = file_get_contents($url);
        if ($data === false) {
            throw new \RuntimeException("Fehler beim Holen der Daten von $url");
        }

        $this->saveToCache($cacheFile, $data);
        echo "Daten erfolgreich geholt und gespeichert: $cacheFile\n";

        return $data;
    }

    private function isCacheValid(string $file): bool
    {
        return file_exists($file) && (time() - filemtime($file)) < $this->cacheTime;
    }

    private function saveToCache(string $file, string $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($file, $data) === false) {
            throw new \RuntimeException("Fehler beim Speichern der Datei: $file");
        }
    }
}