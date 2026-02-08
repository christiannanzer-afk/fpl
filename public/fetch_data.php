<?php

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'FPL\\';
    $base_dir = __DIR__ . '/Classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use FPL\ApiClient;
use FPL\HistoryFetcher;
use FPL\PicksFetcher;
use FPL\AssetDownloader;
use FPL\WatchlistGenerator;
use FPL\GameweekCalculator;

// ====== KONFIGURATION ======
$accessToken = "eyJhbGciOiJSUzI1NiIsImtpZCI6ImRlZmF1bHQifQ.eyJjbGllbnRfaWQiOiJiZmNiYWY2OS1hYWRlLTRjMWItOGYwMC1jMWNiOGExOTMwMzAiLCJpc3MiOiJodHRwczovL2FjY291bnQucHJlbWllcmxlYWd1ZS5jb20vYXMiLCJqdGkiOiJmNTc2ZDE2NC02NWM2LTRhMzEtOTFlNS00NGQ1ZWQ2ZjQ2NDMiLCJpYXQiOjE3NzA0Nzc2ODksImV4cCI6MTc3MDUwNjQ4OSwiYXVkIjpbImh0dHBzOi8vYXBpLnBpbmdvbmUuZXUiXSwic2NvcGUiOiJvcGVuaWQgcHJvZmlsZSBlbWFpbCIsInN1YiI6IjI1YmI4YzViLWUyNDctNDFjYy1hY2NlLTAxZjYxN2E4YTA4MSIsInNpZCI6IjI3NzI3M2VmLTA3YzItNDBhNC04MWFiLTdhZjkyM2EwMjhjMyIsImF1dGhfdGltZSI6MTc3MDQ3NzY4NywiYWNyIjoiMjYyY2U0YjAxZDE5ZGQ5ZDM4NWQyNmJkZGI0Mjk3YjYiLCJhdXRoZW50aWNhdG9yIjoicHdkIiwiaHR0cDovL21lZGlha2luZC5jb20vbWYvdGlkIjoiZGVmYXVsdCIsImVudiI6IjY4MzQwZGUxLWRmYjktNDEyZS05MzdjLTIwMTcyOTg2ZDEyOSIsIm9yZyI6IjNhNjg1MDMyLTgzMjYtNDk2OS1hNzhiLWNjMjk3NzViMTNkNiIsInAxLnJpZCI6ImYwOGRkMTY3LTcyYTUtNGQ5MS05ZGQ3LTliMjU4MDNmYzlkYSJ9.aG7ImS4-gc9L1Nch-mbBgsxAUhmSSkbitAvFTecsqHpBt6plsYV80YvbyWpG7B4VoDbyKmXWNehuzKt_UjP_C1XEDTQIMlC-JU3N9ShzMY6sdXxqNSqFcAW__VHV-4ywI3NJ9wkjGIPBuZg8hEc5auAZJI2V9zOzmrtwhaY0lzgDzbBVqBXeQq8aN9x2ftU3TMmnMQSMbOgMSg0Ytw44Zjon4HoGROSGzNwaSSkzIgthhZrsnqyvE3dlICpWGco5qA0Bkxm5aZRJ7EPdF9aXxOhKzvYoikdvob6gXglcAyuQE5tZl6nk-eX8DvGArE_W8ctq2a-X4rlnJHhgAqEwMA";
$managerId = 12206460;
$cacheTime = 3600; // 1 Stunde

// Pfade
$dataDir = __DIR__ . '/data/';
$photoDir = __DIR__ . '/photos/';
$badgeDir = __DIR__ . '/badges/';
$historyDir = $dataDir . 'history/';
$picksDir = $dataDir . 'picks/';

$bootstrapFile = $dataDir . 'bootstrap-static.json';
$fixturesFile = $dataDir . 'fixtures.json';
$myTeamFile = $dataDir . 'my-team.json';
$allHistFile = $dataDir . 'all_histories.json';
$watchlistFile = __DIR__ . '/watchlist.php';

// ====== AUSFÜHRUNG ======

try {
    echo "=== FPL Data Fetcher ===\n\n";

    // 1. API Client initialisieren
    $apiClient = new ApiClient($accessToken, $cacheTime);

    // 2. Bootstrap-Static holen
    echo "1. Bootstrap-Static...\n";
    $bootstrapData = $apiClient->fetchBootstrap($bootstrapFile);
    $players = $bootstrapData['elements'] ?? [];
    $teams = $bootstrapData['teams'] ?? [];
    $events = $bootstrapData['events'] ?? [];

    // Team-Codes für Fallback
    $teamCodes = [];
    foreach ($teams as $team) {
        $teamCodes[$team['id']] = $team['code'];
    }

    // 3. Fixtures holen
    echo "\n2. Fixtures...\n";
    $apiClient->fetchFixtures($fixturesFile);

    // 4. Aktuelle Gameweek ermitteln
    $gwCalc = new GameweekCalculator($events);
    $currentGw = $gwCalc->getCurrentGameweek();
    echo "\nAktuelle Gameweek: $currentGw\n";

    // 5. Player-Histories holen
    echo "\n5. Player-Histories...\n";
    $historyFetcher = new HistoryFetcher($historyDir, $allHistFile, $cacheTime);
    $historyFetcher->fetchAllHistories($players);

    // 6. Assets herunterladen
    echo "\n6. Assets...\n";
    $assetDownloader = new AssetDownloader();
    $assetDownloader->downloadPlayerPhotos($players, $teamCodes, $photoDir);
    $assetDownloader->downloadTeamBadges($teams, $badgeDir);

    // 7. Watchlist generieren
    echo "\n7. Watchlist generieren...\n";
    $watchlistGen = new WatchlistGenerator($players, $teams, $watchlistFile);
    $watchlistGen->generate();

    echo "\n=== ✅ FERTIG! ===\n";

} catch (\Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
