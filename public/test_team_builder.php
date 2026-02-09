<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Team Builder - Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/team_builder.css" rel="stylesheet">
    <style>
        .test-box {
            padding: 20px;
            margin: 20px;
            border: 2px solid #000;
        }
        .success { background: #d4edda; }
        .error { background: #f8d7da; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Team Builder - Datei Test</h1>
        
        <div class="test-box success">
            <h3>✓ HTML lädt</h3>
            <p>Diese Seite wird angezeigt</p>
        </div>

        <div class="test-box <?= file_exists(__DIR__ . '/assets/css/team_builder.css') ? 'success' : 'error' ?>">
            <h3><?= file_exists(__DIR__ . '/assets/css/team_builder.css') ? '✓' : '✗' ?> CSS Datei</h3>
            <p>Pfad: <?= __DIR__ . '/assets/css/team_builder.css' ?></p>
            <p>Existiert: <?= file_exists(__DIR__ . '/assets/css/team_builder.css') ? 'JA' : 'NEIN' ?></p>
        </div>

        <div class="test-box <?= file_exists(__DIR__ . '/assets/js/team_builder.js') ? 'success' : 'error' ?>">
            <h3><?= file_exists(__DIR__ . '/assets/js/team_builder.js') ? '✓' : '✗' ?> JS Datei</h3>
            <p>Pfad: <?= __DIR__ . '/assets/js/team_builder.js' ?></p>
            <p>Existiert: <?= file_exists(__DIR__ . '/assets/js/team_builder.js') ? 'JA' : 'NEIN' ?></p>
        </div>

        <div class="test-box <?= file_exists(__DIR__ . '/Classes/TeamBuilderConfig.php') ? 'success' : 'error' ?>">
            <h3><?= file_exists(__DIR__ . '/Classes/TeamBuilderConfig.php') ? '✓' : '✗' ?> PHP Klassen</h3>
            <p>Pfad: <?= __DIR__ . '/Classes/' ?></p>
            <?php
            $classes = ['TeamBuilderConfig.php', 'TeamBuilderDataLoader.php', 'TeamManager.php', 'TeamBuilderView.php'];
            foreach ($classes as $class) {
                $exists = file_exists(__DIR__ . '/Classes/' . $class);
                echo "<p>{$class}: " . ($exists ? '✓ JA' : '✗ NEIN') . "</p>";
            }
            ?>
        </div>

        <div class="test-box">
            <h3>Bootstrap Test</h3>
            <button class="btn btn-primary">Bootstrap Button</button>
            <p>Wenn dieser Button blau ist, lädt Bootstrap korrekt.</p>
        </div>

        <div class="test-box">
            <h3>Custom CSS Test</h3>
            <div class="player-item" style="margin: 10px 0;">
                <p>Wenn dieser Text spezielles Styling hat, lädt das CSS.</p>
            </div>
        </div>

        <div class="test-box">
            <h3>JavaScript Test</h3>
            <button onclick="alert('JavaScript funktioniert!')">Test JS</button>
        </div>

        <div class="test-box">
            <h3>Team Builder Link</h3>
            <a href="team_builder.php" class="btn btn-success">Zum Team Builder</a>
        </div>
    </div>
</body>
</html>
