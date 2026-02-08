<?php
$start = microtime(true);

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

use FPL\Config;
use FPL\DataLoader;
use FPL\GameweekCalculator;
use FPL\TableGenerator;
use FPL\FormGenerator;

// Config laden
$config = new Config();

// Daten laden
$dataLoader = new DataLoader($config);
$dataLoader->loadAll();

// GET-Parameter verarbeiten
$minPoints = isset($_GET['min_points']) ? (int)$_GET['min_points'] : $config->get('default_min_points');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $config->get('default_limit');
$sortBy = $_GET['sort_by'] ?? 'total_points';
$sortDir = $_GET['sort_dir'] ?? 'desc';
$fixtureCountGet = $_GET['fixture_count'] ?? (string)$config->get('default_fixture_count');
$fixtureCount = $fixtureCountGet === 'all' ? 'all' : (int)$fixtureCountGet;
$filterTeam = isset($_GET['filter_team']) ? (int)$_GET['filter_team'] : 0;
$filterPosition = isset($_GET['filter_position']) ? (int)$_GET['filter_position'] : 0;
$filterWatchlist = isset($_GET['filter_watchlist']) && $_GET['filter_watchlist'] === '1';
$filterDreamTeam = isset($_GET['filter_dreamteam']) && $_GET['filter_dreamteam'] === '1';
$filterName = isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '';
$gwDream = isset($_GET['gw_dream']) ? (int)$_GET['gw_dream'] : 0;
$histHighlight = isset($_GET['hist_highlight']) ? (int)$_GET['hist_highlight'] : 0;
$fixtureDetails = isset($_GET['fixture_details']) && $_GET['fixture_details'] === '1';
$playerDetails = isset($_GET['player_details']) && $_GET['player_details'] === '1';
$histCountGet = $_GET['hist_count'] ?? (string)$config->get('default_history_count');

// Gameweek Calculator
$gwCalc = new GameweekCalculator($dataLoader->getEvents());
$currentGw = $gwCalc->getCurrentGameweek();
$nextGW = $gwCalc->getNextGameweek();

// History Count berechnen
$maxHistCount = $currentGw;
$histCount = $histCountGet === 'all' ? $maxHistCount : (int)$histCountGet;

// Fixture Count berechnen
$maxFixtureCount = 38 - $currentGw;
if ($fixtureCount === 'all') {
    $fixtureCount = $maxFixtureCount;
} else {
    $fixtureCount = (int)$fixtureCount;
}

// Dream Team für GW laden
if ($filterDreamTeam && $gwDream === 0) {
    $gwDream = $nextGW;
}
$dataLoader->loadDreamTeam($gwDream);

// Parameter-Array für Generatoren
$params = [
    'minPoints' => $minPoints,
    'limit' => $limit,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'histCount' => $histCount,
    'histCountGet' => $histCountGet,
    'fixtureCount' => $fixtureCount,
    'fixtureCountGet' => $fixtureCountGet,
    'filterTeam' => $filterTeam,
    'filterPosition' => $filterPosition,
    'filterWatchlist' => $filterWatchlist,
    'filterDreamTeam' => $filterDreamTeam,
    'filterName' => $filterName,
    'filterWatchlistStr' => $filterWatchlist ? '1' : '',
    'filterDreamTeamStr' => $filterDreamTeam ? '1' : '',
    'gwDream' => $gwDream,
    'histHighlight' => $histHighlight,
    'fixtureDetails' => $fixtureDetails,
    'playerDetails' => $playerDetails,
    'currentGw' => $currentGw,
    'nextGW' => $nextGW,
];

// HTMX-Request → nur Content
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    ?>
    <div id="content">
        <div class="container-fluid my-3">
            <?php
            $formGen = new FormGenerator($config, $params, $dataLoader->getTeams());
            echo $formGen->generate();
            ?>
        </div>
        <div class="table-responsive">
            <?php
            $tableGen = new TableGenerator($config, $dataLoader, $params);
            echo $tableGen->generate();
            ?>
        </div>
    </div>
    <?php
    exit;
}

// Vollständige Seite
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <title>FPL Dashboard</title>
    <style>
        body { font-size: 14px; }
        th.sortable { cursor: pointer; }
        .clickable { cursor: pointer; }
        td:hover { cursor: default; }
        td { white-space: nowrap; padding-bottom:0!important; }
        .table-responsive { overflow-x: auto; }
        #player-table th:first-child,
        #player-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        #player-table thead th:first-child {
            background-color: #f8f9fa;
            z-index: 3;
        }
        .border-left-bold { border-left: 1px solid #dee2e6 !important; }
        .border-top-dark { border-top: 2px solid #000 !important; }
        #player-table th:first-child::after,
        #player-table td:first-child::after {
            content: '';
            position: absolute;
            top: 0; right: -5px;
            width: 5px; height: 100%;
            box-shadow: 3px 0 5px -2px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div id="content">
        <div class="container-fluid my-3">
            <?php
            $formGen = new FormGenerator($config, $params, $dataLoader->getTeams());
            echo $formGen->generate();
            ?>
        </div>
        <div class="table-responsive">
            <?php
            $tableGen = new TableGenerator($config, $dataLoader, $params);
            echo $tableGen->generate();
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let tooltipObserver;
    function initializeTooltipObserver() {
        if (!tooltipObserver) {
            tooltipObserver = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !entry.target._tooltipInitialized) {
                        new bootstrap.Tooltip(entry.target);
                        entry.target._tooltipInitialized = true;
                    }
                });
            }, { threshold: 0.1 });
        }
    }
    function observeTooltips(elements) {
        elements.forEach(el => {
            if (!el._tooltipInitialized) tooltipObserver.observe(el);
        });
    }
    document.addEventListener("htmx:beforeSwap", evt => {
        evt.detail.target.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            bootstrap.Tooltip.getInstance(el)?.dispose();
            el._tooltipInitialized = false;
        });
    });
    function initFilterListener(content) {
        const fdt = content.querySelector('#filter_dreamteam');
        if (fdt) {
            fdt.addEventListener('change', function() {
                const form = this.closest('form');
                const sb = form.querySelector('[name="sort_by"]');
                const sd = form.querySelector('[name="sort_dir"]');
                const gw = form.querySelector('[name="gw_dream"]');
                if (this.checked) {
                    sb.value = 'dream_order';
                    sd.value = 'asc';
                    gw.value = <?php echo $nextGW ?: 1; ?>;
                } else {
                    sb.value = 'total_points';
                    sd.value = 'desc';
                    gw.value = 0;
                }
                htmx.trigger(form, 'change');
            });
        }
    }
    htmx.onLoad(content => {
        observeTooltips(content.querySelectorAll('[data-bs-toggle="tooltip"]'));
        initFilterListener(content);
    });
    document.addEventListener("DOMContentLoaded", () => {
        initializeTooltipObserver();
        observeTooltips(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        initFilterListener(document);
    });
    </script>

    <?php echo "Time: " . number_format(microtime(true) - $start, 4) . "s"; ?>
</body>
</html>
