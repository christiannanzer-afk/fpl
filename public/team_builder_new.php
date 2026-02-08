<?php

// Nutze den existierenden Autoloader wenn vorhanden
if (file_exists(__DIR__ . '/autoload.php')) {
    require_once __DIR__ . '/autoload.php';
} else {
    // Fallback: Eigener Autoloader
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
}

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
