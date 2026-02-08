<?php
// Debug: Prüfe ob Dateien existieren
$requiredFiles = [
    'Classes/TeamBuilderConfig.php',
    'Classes/TeamBuilderDataLoader.php',
    'Classes/TeamManager.php',
    'Classes/TeamBuilderView.php'
];

echo "<!-- Debug Info:\n";
echo "Current directory: " . __DIR__ . "\n";
foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    echo "$file: " . (file_exists($fullPath) ? "EXISTS" : "MISSING") . " ($fullPath)\n";
}
echo "-->\n\n";

// Direktes Einbinden der Klassen
require_once __DIR__ . '/Classes/TeamBuilderConfig.php';
require_once __DIR__ . '/Classes/TeamBuilderDataLoader.php';
require_once __DIR__ . '/Classes/TeamManager.php';
require_once __DIR__ . '/Classes/TeamBuilderView.php';

use FPL\TeamBuilderConfig;
use FPL\TeamBuilderDataLoader;
use FPL\TeamManager;
use FPL\TeamBuilderView;

// Config erstellen
$config = new TeamBuilderConfig();

// Team Manager für AJAX-Requests
$teamManager = new TeamManager($config);
$teamManager->handleAjaxRequest();

// Data Loader
$dataLoader = new TeamBuilderDataLoader($config);
$dataLoader->loadData();

// View Generator
$view = new TeamBuilderView($config, $dataLoader);
?>
<?= $view->renderHead() ?>
<body>
<div class="container-fluid">
    <div class="row g-4">
        <!-- LINKS: Spielerliste -->
        <div class="col-lg-3">
            <div class="card shadow">
                <div class="card-body">
                    <input id="search" class="form-control form-control-lg mb-4" placeholder="Spieler oder Club suchen..." autofocus>
                    <?= $view->renderPlayerList() ?>
                </div>
            </div>
        </div>

        <!-- RECHTS: Team Builder -->
        <div class="col-lg-9">
            <?= $view->renderSquadBuilder() ?>
        </div>
    </div>
</div>

<div id="toast">Fehler</div>

<?= $view->renderScripts() ?>
</body>
</html>
