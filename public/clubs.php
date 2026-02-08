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
use FPL\LeagueTableCalculator;
use FPL\FixtureProcessor;
use FPL\TeamFixtureProcessor;
use FPL\TeamStats;
use FPL\TeamSorter;
use FPL\TeamTableGenerator;
use FPL\FixtureColorCalculator;
use FPL\FormGenerator;

// Config laden
$config = new Config();

// Daten laden
$dataLoader = new DataLoader($config);
$dataLoader->loadAll();

// GET-Parameter
$sortBy = $_GET['sort_by'] ?? 'rank';
$sortDir = $_GET['sort_dir'] ?? 'asc';
$histCountGet = $_GET['hist_count'] ?? '3';
$fixtureCountGet = $_GET['fixture_count'] ?? '3';
$fixtureDetails = isset($_GET['fixture_details']) && $_GET['fixture_details'] === '1';

// Gameweeks
$gwCalc = new GameweekCalculator($dataLoader->getEvents());
$currentGw = $gwCalc->getCurrentGameweek();
$nextGW = $gwCalc->getNextGameweek();

// History & Fixture Counts
$maxHistCount = $currentGw;
$histCount = $histCountGet === 'all' ? $maxHistCount : (int)$histCountGet;
$maxFixtureCount = 38 - $currentGw;
$fixtureCount = $fixtureCountGet === 'all' ? $maxFixtureCount : (int)$fixtureCountGet;

// League Table
$leagueCalc = new LeagueTableCalculator($dataLoader->getTeams(), $dataLoader->getFixtures());
$leagueTable = $leagueCalc->getTable();
$ranks = $leagueCalc->getRanks();

// Numerical ranks für Sortierung
$sorted = $leagueTable;
usort($sorted, function($a, $b) {
    if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
    if ($a['gd'] != $b['gd']) return $b['gd'] - $a['gd'];
    return $b['gf'] - $a['gf'];
});
$numericalRanks = [];
foreach ($sorted as $index => $stats) {
    $numericalRanks[$stats['team_id']] = $index + 1;
}

// Team Names & Codes
// Team Names & Codes
$teamNames = [];
$teamCodes = [];
$positionMap = [];
$teamShortNames = [];
foreach ($dataLoader->getTeams() as $team) {
    $teamNames[$team['id']] = $team['short_name'];
    $teamCodes[$team['id']] = $team['code'];
    $positionMap[$team['id']] = $team['position'] ?? 0;
    $teamShortNames[$team['id']] = $team['short_name'];
}
$idByShort = array_flip($teamShortNames);

// Fixture Processing
$fixtureProcessor = new FixtureProcessor(
    $dataLoader->getFixtures(),
    $teamNames,
    $nextGW
);

// Recent Results
$recentResults = [];
for ($teamId = 1; $teamId <= count($dataLoader->getTeams()); $teamId++) {
    $recentResults[$teamId] = $fixtureProcessor->getRecentResults($teamId);
}

// Team Fixture Processor
$teamFixtureProc = new TeamFixtureProcessor(
    $dataLoader->getFixtures(),
    $teamNames,
    $teamCodes
);

$pastFixturesMap = $teamFixtureProc->getAllPastFixturesMap($currentGw);
$nextFixtures = $teamFixtureProc->getAllUpcomingFixtures($nextGW, $fixtureCount);

// Color Calculator
$colorCalc = new FixtureColorCalculator(
    $config,
    $leagueCalc->getPointsMap(),
    $positionMap,
    $idByShort
);

// Teams sortieren
$teams = $dataLoader->getTeams();
$sorter = new TeamSorter($sortBy, $sortDir);
$sorter->sort($teams, $teamNames, $numericalRanks, $leagueTable, []); // Leeres Array für teamPicks


// Parameter-Array
$params = [
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'histCount' => $histCount,
    'histCountGet' => $histCountGet,
    'fixtureCount' => $fixtureCount,
    'fixtureCountGet' => $fixtureCountGet,
    'fixtureDetails' => $fixtureDetails,
    'currentGw' => $currentGw,
    'nextGW' => $nextGW,
];

// Table Generator
$tableGen = new TeamTableGenerator($config, $params);

// HTMX-Request
if (isset($_SERVER['HTTP_HX_REQUEST'])) {
    ?>
    <div id="content">
        <div class="container-fluid my-3">
            <?php
            // Simple form (reuse FormGenerator later if needed)
            ?>
            <form id="filter-form" hx-get="?" hx-target="#content" hx-swap="outerHTML" hx-trigger="change from:form, submit">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="hist_count" class="form-label">History</label>
                        <select name="hist_count" id="hist_count" class="form-select form-select-sm">
                            <option value="0" <?php echo $histCountGet === '0' ? 'selected' : ''; ?>>0</option>
                            <option value="3" <?php echo $histCountGet === '3' ? 'selected' : ''; ?>>3</option>
                            <option value="5" <?php echo $histCountGet === '5' ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $histCountGet === '10' ? 'selected' : ''; ?>>10</option>
                            <option value="all" <?php echo $histCountGet === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="fixture_count" class="form-label">Fixtures</label>
                        <select name="fixture_count" id="fixture_count" class="form-select form-select-sm">
                            <option value="0" <?php echo $fixtureCountGet === '0' ? 'selected' : ''; ?>>0</option>
                            <option value="3" <?php echo $fixtureCountGet === '3' ? 'selected' : ''; ?>>3</option>
                            <option value="5" <?php echo $fixtureCountGet === '5' ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $fixtureCountGet === '10' ? 'selected' : ''; ?>>10</option>
                            <option value="all" <?php echo $fixtureCountGet === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="fixture_details" name="fixture_details" value="1" <?php echo $fixtureDetails ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="fixture_details">Fixture Details</label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sortBy); ?>">
                <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDir); ?>">
            </form>
        </div>
        <div class="table-responsive">
            <table id="team-table" class="table table-striped">
                <?php echo $tableGen->generateThead(); ?>
                <?php echo $tableGen->generateTbody($teams, $teamNames, $teamCodes, $leagueTable, $ranks, $pastFixturesMap, $nextFixtures, $recentResults, $colorCalc); ?>
            </table>
        </div>
    </div>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <title>FPL Teams</title>
    <style>
        body { font-size: 14px; }
        th.sortable { cursor: pointer; }
        td { white-space: nowrap; padding-bottom:0!important; height: 42px;}
        .table-responsive { overflow-x: auto; }
        #team-table th:first-child,
        #team-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        #team-table thead th:first-child {
            background-color: #f8f9fa;
            z-index: 3;
        }
        .border-left-bold { border-left: 1px solid #dee2e6 !important; }
        #team-table th:first-child::after,
        #team-table td:first-child::after {
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
            <form id="filter-form" hx-get="?" hx-target="#content" hx-swap="outerHTML" hx-trigger="change from:form, submit">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="hist_count" class="form-label">History</label>
                        <select name="hist_count" id="hist_count" class="form-select form-select-sm">
                            <option value="0" <?php echo $histCountGet === '0' ? 'selected' : ''; ?>>0</option>
                            <option value="3" <?php echo $histCountGet === '3' ? 'selected' : ''; ?>>3</option>
                            <option value="5" <?php echo $histCountGet === '5' ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $histCountGet === '10' ? 'selected' : ''; ?>>10</option>
                            <option value="all" <?php echo $histCountGet === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="fixture_count" class="form-label">Fixtures</label>
                        <select name="fixture_count" id="fixture_count" class="form-select form-select-sm">
                            <option value="0" <?php echo $fixtureCountGet === '0' ? 'selected' : ''; ?>>0</option>
                            <option value="3" <?php echo $fixtureCountGet === '3' ? 'selected' : ''; ?>>3</option>
                            <option value="5" <?php echo $fixtureCountGet === '5' ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $fixtureCountGet === '10' ? 'selected' : ''; ?>>10</option>
                            <option value="all" <?php echo $fixtureCountGet === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="fixture_details" name="fixture_details" value="1" <?php echo $fixtureDetails ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="fixture_details">Fixture Details</label>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sortBy); ?>">
                <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDir); ?>">
            </form>
        </div>
        <div class="table-responsive">
            <table id="team-table" class="table table-striped">
                <?php echo $tableGen->generateThead(); ?>
                <?php echo $tableGen->generateTbody($teams, $teamNames, $teamCodes, $leagueTable, $ranks, $pastFixturesMap, $nextFixtures, $recentResults, $colorCalc); ?>
            </table>
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
    htmx.onLoad(content => {
        observeTooltips(content.querySelectorAll('[data-bs-toggle="tooltip"]'));
    });
    document.addEventListener("DOMContentLoaded", () => {
        initializeTooltipObserver();
        observeTooltips(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    });
    </script>

    <?php echo "Time: " . number_format(microtime(true) - $start, 4) . "s"; ?>
</body>
</html>
