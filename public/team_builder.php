<?php
$bootstrapFile = __DIR__.'/data/bootstrap-static.json';
if (!file_exists($bootstrapFile)) die('bootstrap-static.json fehlt!');

$data = json_decode(file_get_contents($bootstrapFile), true);
$players = $data['elements'] ?? [];
$teams = $data['teams'] ?? [];

$teamShort = [];
foreach ($teams as $t) {
    $code = $t['short_name'] ?? substr($t['name'], 0, 3);
    $teamShort[$t['id']] = strtoupper($code);
}

$posMap = [1=>'GK', 2=>'DEF', 3=>'MID', 4=>'FWD'];
usort($players, fn($a,$b) => $b['total_points'] <=> $a['total_points']);

// Default GW
$defaultGw = 12;

// AJAX: Lade oder Speichere Team pro GW
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['load_team'])) {
        $gw = intval($_POST['gw']);
        if ($gw < 1 || $gw > 38) {
            echo json_encode(['success' => false, 'error' => 'Ungültiger GW']);
            exit;
        }
        $file = __DIR__.'/dream_teams/dream_team_GW'.$gw.'.json';
        if (file_exists($file)) {
            $ids = json_decode(file_get_contents($file), true);
            echo json_encode(['success' => true, 'ids' => $ids ?: []]);
        } else {
            echo json_encode(['success' => true, 'ids' => []]);
        }
        exit;
    }
    if (isset($_POST['save_team'])) {
        $gw = intval($_POST['gw']);
        if ($gw < 1 || $gw > 38) {
            echo json_encode(['success' => false, 'error' => 'Ungültiger GW']);
            exit;
        }
        $ids = json_decode($_POST['ids'], true);
        if (is_array($ids) && count($ids) === 15) {
            $dir = __DIR__.'/dream_teams';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $file = $dir.'/dream_team_GW'.$gw.'.json';
            file_put_contents($file, json_encode($ids, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ungültige IDs']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FPL Team Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {background:#f0f2f5;font-family:system-ui,sans-serif;margin:0;padding:20px 0;}
        .container-fluid {max-width:1600px;}
        .player-list {max-height:85vh;overflow-y:auto;padding-right:8px;}
        .player-item {display:flex;align-items:center;background:#fff;padding:10px 12px;margin:6px 0;border-radius:12px;
                      box-shadow:0 2px 6px rgba(0,0,0,.1);transition:all .2s;cursor:grab;user-select:none;}
        .player-item:hover {background:#e3f2fd;transform:translateY(-2px);}
        .player-item.dragging {opacity:0.4;}
        .player-item.in-squad {opacity:0.35;pointer-events:none;}
        .player-item img {width:44px;height:44px;border-radius:50%;object-fit:cover;margin-right:12px;border:2px solid #eee; draggable: false;}
        .player-item .name {font-weight:600;font-size:.95rem;}
        .player-item .pts {font-size:.8rem;color:#555;margin-top:2px;}
        .player-item .pos {font-weight:bold;font-size:.8rem;color:#007bff;margin-left:auto;margin-right:8px;}
        .player-item .club {font-size:.78rem;color:#666;}

        .squad-area {background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);padding:1.5rem;height:95vh;overflow-y:auto;}
        .drop-zone {background:#f8f9fa;border:2px dashed #ccc;border-radius:12px;min-height:80px;padding:12px;
                    transition:all .2s;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;margin-bottom:20px;}
        .drop-zone.over {border-color:#007bff;background:#e3f2fd;}
        .zone-wrapper {position:relative;}
        .zone-label {position:absolute;top:-12px;left:16px;background:white;padding:0 8px;
                     font-weight:600;font-size:.9rem;color:#333;}

        .squad-player {background:#fff;padding:8px 10px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.1);
                       font-size:.85rem;display:flex;align-items:center;gap:8px;width:fit-content;cursor:grab;}
        .squad-player:hover {background:#e3f2fd;}
        .squad-player.dragging {opacity:0.4;}
        .squad-player img {width:32px;height:32px;border-radius:50%; draggable: false;}
        .squad-player .pos {font-weight:bold;color:#007bff;font-size:.75rem;}

        /* NEU: Bank-spezifische Styles (kleiner, vertikal) */
        .bench-zone {min-height:60px; padding:8px; gap:4px;}
        .bench-squad-player {padding:6px 8px; font-size:.75rem; gap:6px;}
        .bench-squad-player img {width:24px; height:24px;}
        .bench-squad-player .pos {font-size:.65rem;}
        .bench-squad-player div div {font-size:.7rem; font-weight:600;}
        .bench-squad-player div div + div {font-size:.65rem; color:#666;}

        .counter {font-size:.9rem;font-weight:600;color:#333;margin-top:8px;text-align:center;}
        .counter.valid {color:#28a745;}
        .counter.invalid {color:#dc3545;}

        .card {border:none;border-radius:16px;overflow:hidden;}
        .card-body {padding:1.5rem;}

        #toast {position:fixed;bottom:20px;right:20px;background:#333;color:#fff;padding:12px 20px;
                border-radius:8px;opacity:0;transition:opacity .4s;z-index:9999;font-size:.9rem;
                box-shadow:0 4px 12px rgba(0,0,0,.3);}

        #value-display {text-align:center; margin-bottom:10px; font-size:1.1rem; font-weight:600;}
        .value-total {color:#333;}
        .value-diff {font-weight:bold; margin-left:5px;}
        .value-diff.over {color:#dc3545;} /* Rot für Überziehung */
        .value-diff.under {color:#28a745;} /* Grün für Unterziehung */

        #gw-select {max-width: 150px; margin: 0 auto 1rem;}
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row g-4">
        <!-- LINKS: Spielerliste -->
        <div class="col-lg-3">
            <div class="card shadow">
                <div class="card-body">
                    <input id="search" class="form-control form-control-lg mb-4" placeholder="Spieler oder Club suchen..." autofocus>
                    <div id="list" class="player-list">
                        <?php foreach($players as $p):
                            $pos = $posMap[$p['element_type']] ?? '???';
                            $photo = "photos/{$p['code']}.png";
                            $club = $teamShort[$p['team']] ?? '???';
                        ?>
                        <div class="player-item" draggable="true"
                             data-id="<?= $p['id'] ?>"
                             data-name="<?= htmlspecialchars($p['web_name']) ?>"
                             data-club="<?= $club ?>"
                             data-pos="<?= $pos ?>"
                             data-team-id="<?= $p['team'] ?>"
                             data-photo="<?= $photo ?>"
                             data-cost="<?= ($p['now_cost'] ?? 0) / 10 ?>">
                            <img src="<?= $photo ?>" alt="" draggable="false">
                            <div><div class="name"><?= htmlspecialchars($p['web_name']) ?></div>
                                 <div class="pts"><?= $p['total_points'] ?> Pkt, <?= $p['now_cost']/10 ?>£</div></div>
                            <div class="pos"><?= $pos ?></div>
                            <div class="club"><?= $club ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECHTS: Squad-Area mit Row für Startelf + Bank -->
        <div class="col-lg-9">
            <div class="squad-area">
                <select id="gw-select" class="form-select">
                    <?php for($i=1; $i<=38; $i++): ?>
                        <option value="<?= $i ?>">GW<?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <h4 class="mb-4 text-center">
                    Gesamt: <span id="count-total">0</span>/15
                </h4>
                <div id="value-display">
                    Value: <span id="value-total" class="value-total">0.0</span>M <span id="value-diff" class="value-diff"></span>
                </div>
                <div class="counter mb-4" id="status">Zieh Spieler hierher</div>

                <div class="row h-100">
                    <!-- LINKS: Startelf (vertikal, col-8) -->
                    <div class="col-10 pe-3">
                        <!-- Bereich 1: GK -->
                        <div class="zone-wrapper"><div class="zone-label">GK (0/1)</div>
                            <div id="zone-gk" class="drop-zone"></div></div>

                        <!-- Bereich 2: DEF -->
                        <div class="zone-wrapper"><div class="zone-label">DEF (0/5)</div>
                            <div id="zone-def" class="drop-zone"></div></div>

                        <!-- Bereich 3: MID -->
                        <div class="zone-wrapper"><div class="zone-label">MID (0/5)</div>
                            <div id="zone-mid" class="drop-zone"></div></div>

                        <!-- Bereich 4: FWD -->
                        <div class="zone-wrapper"><div class="zone-label">FWD (0/3)</div>
                            <div id="zone-fwd" class="drop-zone"></div></div>
                    </div>

                    <!-- RECHTS: Bank (vertikal, col-4, kleiner) -->
                    <div class="col-2 ps-3">
                        <div class="d-flex flex-column h-100">
                            <!-- Bereich 5: GK Ersatz -->
                            <div class="zone-wrapper mb-3"><div class="zone-label">GK (0/1)</div>
                                <div id="zone-bench-gk" class="drop-zone bench-zone"></div></div>

                            <!-- Bereich 6: Feld Ersatz -->
                            <div class="zone-wrapper flex-grow-1"><div class="zone-label">Feld (0/3)</div>
                                <div id="zone-bench-field" class="drop-zone bench-zone"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast">Fehler</div>

<script>
    const defaultGw = <?= $defaultGw ?>;
    const squad = { gk:[], def:[], mid:[], fwd:[], 'bench-gk':[], 'bench-field':[] };
    let wasValid = false; // Track vorherigen Status, um nur bei neuem Valid zu speichern
    let squadTotalCost = 0; // Track Gesamtkosten

    // Spieler-Daten aus PHP
    const allPlayers = <?php
        $playerData = [];
        foreach ($players as $p) {
            $pos = $posMap[$p['element_type']] ?? '???';
            $photo = "photos/{$p['code']}.png";
            $club = $teamShort[$p['team']] ?? '???';
            $playerData[$p['id']] = [
                'id' => $p['id'],
                'name' => $p['web_name'],
                'pos' => $pos,
                'club' => $club,
                'teamId' => $p['team'],
                'photo' => $photo,
                'cost' => ($p['now_cost'] ?? 0) / 10 // Kosten in Mio.
            ];
        }
        echo json_encode($playerData);
    ?>;

    let dreamTeamIds = [];

    function getZoneKey(id) {
        return { 'zone-gk':'gk', 'zone-def':'def', 'zone-mid':'mid', 'zone-fwd':'fwd',
                 'zone-bench-gk':'bench-gk', 'zone-bench-field':'bench-field' }[id];
    }

    function getZoneCounts() {
        return {
            gk: squad.gk.length,
            def: squad.def.length,
            mid: squad.mid.length,
            fwd: squad.fwd.length,
            'bench-gk': squad['bench-gk'].length,
            'bench-field': squad['bench-field'].length
        };
    }

    function getPosCounts() {
        return {
            GK: squad.gk.length + squad['bench-gk'].length,
            DEF: squad.def.length + squad['bench-field'].filter(p => p.pos === 'DEF').length,
            MID: squad.mid.length + squad['bench-field'].filter(p => p.pos === 'MID').length,
            FWD: squad.fwd.length + squad['bench-field'].filter(p => p.pos === 'FWD').length
        };
    }

    function isInSquad(playerId) {
        return Object.values(squad).flat().some(p => p.id == playerId);
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.opacity = '1';
        clearTimeout(t.hideTimeout);
        t.hideTimeout = setTimeout(() => t.style.opacity = '0', 3000);
    }

    function saveTeam(gw) {
        const ids = [...squad.gk, ...squad.def, ...squad.mid, ...squad.fwd, ...squad['bench-gk'], ...squad['bench-field']].map(p => parseInt(p.id));
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `save_team=1&gw=${gw}&ids=${encodeURIComponent(JSON.stringify(ids))}`
        }).then(response => response.json()).then(data => {
            if (data.success) {
                showToast(`Team für GW${gw} gespeichert!`);
            } else {
                showToast('Fehler beim Speichern: ' + (data.error || 'Unbekannt'));
            }
        }).catch(err => showToast('Netzwerkfehler: ' + err.message));
    }

    // ========== CLEAR SQUAD ==========
    function clearSquad() {
        Object.keys(squad).forEach(key => squad[key] = []);
        squadTotalCost = 0;
        document.querySelectorAll('.drop-zone').forEach(zone => zone.innerHTML = '');
        wasValid = false;
        updateUI();
    }

    // ========== LOAD TEAM FROM SERVER ==========
    function loadTeamFromServer(gw) {
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `load_team=1&gw=${gw}`
        }).then(response => response.json()).then(data => {
            if (data.success) {
                dreamTeamIds = data.ids;
                placeDreamTeam(dreamTeamIds);
            } else {
                dreamTeamIds = [];
                clearSquad();
                showToast(`Kein Team für GW${gw} gefunden.`);
            }
        }).catch(err => {
            showToast('Fehler beim Laden: ' + err.message);
            clearSquad();
        });
    }

    // ========== PLACE DREAM TEAM ==========
    function placeDreamTeam(ids) {
        clearSquad(); // Stelle sicher, dass es leer ist
        if (!Array.isArray(ids) || ids.length !== 15) return;

        // Zone-Referenzen
        const zones = {
            gk: document.getElementById('zone-gk'),
            def: document.getElementById('zone-def'),
            mid: document.getElementById('zone-mid'),
            fwd: document.getElementById('zone-fwd'),
            'bench-gk': document.getElementById('zone-bench-gk'),
            'bench-field': document.getElementById('zone-bench-field')
        };

        // Zähle, wie viele pro Zone schon drin sind (initial 0)
        let placed = { gk:0, def:0, mid:0, fwd:0, 'bench-gk':0, 'bench-field':0 };
        let placed_start_total = 0; // Tracker für Startelf-Gesamt (max 11)

        ids.forEach(id => {
            const p = allPlayers[id];
            if (!p) return;

            let targetZone = null;
            let key = null;

            if (p.pos === 'GK') {
                if (placed.gk < 1 && placed_start_total < 11) { 
                    targetZone = zones.gk; 
                    key = 'gk'; 
                }
                else if (placed['bench-gk'] < 1) { 
                    targetZone = zones['bench-gk']; 
                    key = 'bench-gk'; 
                }
            } else if (p.pos === 'DEF') {
                if (placed.def < 5 && placed_start_total < 11) { 
                    targetZone = zones.def; 
                    key = 'def'; 
                }
                else if (placed['bench-field'] < 3) { 
                    targetZone = zones['bench-field']; 
                    key = 'bench-field'; 
                }
            } else if (p.pos === 'MID') {
                if (placed.mid < 5 && placed_start_total < 11) { 
                    targetZone = zones.mid; 
                    key = 'mid'; 
                }
                else if (placed['bench-field'] < 3) { 
                    targetZone = zones['bench-field']; 
                    key = 'bench-field'; 
                }
            } else if (p.pos === 'FWD') {
                if (placed.fwd < 3 && placed_start_total < 11) { 
                    targetZone = zones.fwd; 
                    key = 'fwd'; 
                }
                else if (placed['bench-field'] < 3) { 
                    targetZone = zones['bench-field']; 
                    key = 'bench-field'; 
                }
            }

            if (targetZone && key) {
                addToSquad(targetZone, p);
                placed[key]++;
                if (['gk', 'def', 'mid', 'fwd'].includes(key)) {
                    placed_start_total++;
                }
            }
        });

        updateUI();
    }

    function isValidDrop(zone, player, replaceId = null) {
        const key = getZoneKey(zone.id);
        const pos = player.pos;
        const counts = getZoneCounts();
        const posCounts = getPosCounts();

        const violations = [];

        // Position passt nicht zur Zone
        if (key === 'gk' && pos !== 'GK') violations.push('Falsche Position: Nur GK hier');
        if (key === 'def' && pos !== 'DEF') violations.push('Falsche Position: Nur DEF hier');
        if (key === 'mid' && pos !== 'MID') violations.push('Falsche Position: Nur MID hier');
        if (key === 'fwd' && pos !== 'FWD') violations.push('Falsche Position: Nur FWD hier');
        if (key === 'bench-gk' && pos !== 'GK') violations.push('Falsche Position: Nur Ersatz-GK hier');
        if (key === 'bench-field' && pos === 'GK') violations.push('Falsche Position: Kein GK auf Feld-Ersatzbank');

        if (violations.length > 0) return { valid: false, reason: violations.join('; ') };

        // Simuliere Drop (inkl. Ersetzen: Wenn replaceId, subtrahiere den ersetzten Spieler)
        const tempZoneCounts = { ...counts };
        const tempPosCounts = { ...posCounts };
        if (replaceId) {
            // Finde und subtrahiere den ersetzten Spieler
            const replacedPlayer = Object.values(squad).flat().find(p => p.id == replaceId);
            if (replacedPlayer) {
                const repKey = Object.keys(squad).find(k => squad[k].some(p => p.id == replaceId));
                if (repKey === 'gk') { tempZoneCounts.gk--; tempPosCounts.GK--; }
                else if (repKey === 'def') { tempZoneCounts.def--; tempPosCounts.DEF--; }
                else if (repKey === 'mid') { tempZoneCounts.mid--; tempPosCounts.MID--; }
                else if (repKey === 'fwd') { tempZoneCounts.fwd--; tempPosCounts.FWD--; }
                else if (repKey === 'bench-gk') { tempZoneCounts['bench-gk']--; tempPosCounts.GK--; }
                else if (repKey === 'bench-field') { 
                    tempZoneCounts['bench-field']--; 
                    if (replacedPlayer.pos === 'DEF') tempPosCounts.DEF--;
                    else if (replacedPlayer.pos === 'MID') tempPosCounts.MID--;
                    else if (replacedPlayer.pos === 'FWD') tempPosCounts.FWD--;
                }
            }
        }
        // Füge neuen hinzu
        if (key === 'gk') { tempZoneCounts.gk++; tempPosCounts.GK++; }
        else if (key === 'def') { tempZoneCounts.def++; tempPosCounts.DEF++; }
        else if (key === 'mid') { tempZoneCounts.mid++; tempPosCounts.MID++; }
        else if (key === 'fwd') { tempZoneCounts.fwd++; tempPosCounts.FWD++; }
        else if (key === 'bench-gk') { tempZoneCounts['bench-gk']++; tempPosCounts.GK++; }
        else if (key === 'bench-field') {
            tempZoneCounts['bench-field']++;
            if (pos === 'DEF') tempPosCounts.DEF++;
            else if (pos === 'MID') tempPosCounts.MID++;
            else if (pos === 'FWD') tempPosCounts.FWD++;
        }

        // Zone-Kapazität überschritten (nach Sim)
        const zoneMax = { gk:1, def:5, mid:5, fwd:3, 'bench-gk':1, 'bench-field':3 };
        if (tempZoneCounts[key] > zoneMax[key]) violations.push(`Zone voll: ${key.toUpperCase()} (${tempZoneCounts[key]}/${zoneMax[key]})`);

        // Startelf max 11 (für Zonen 1-4)
        const tempStart11 = tempZoneCounts.gk + tempZoneCounts.def + tempZoneCounts.mid + tempZoneCounts.fwd;
        if (['gk', 'def', 'mid', 'fwd'].includes(key) && tempStart11 > 11) violations.push('Startelf voll (max 11)');

        // Max pro Position (global)
        if (tempPosCounts.GK > 2) violations.push('Zu viele GK (max 2)');
        if (tempPosCounts.DEF > 5) violations.push('Zu viele DEF (max 5)');
        if (tempPosCounts.MID > 5) violations.push('Zu viele MID (max 5)');
        if (tempPosCounts.FWD > 3) violations.push('Zu viele FWD (max 3)');

        // Totals für Startelf-Positionen
        if (tempZoneCounts.def + tempZoneCounts.mid > 9) violations.push('DEF + MID in Startelf >9');
        if (tempZoneCounts.def + tempZoneCounts.fwd > 8) violations.push('DEF + FWD in Startelf >8');
        if (tempZoneCounts.mid + tempZoneCounts.fwd > 8) violations.push('MID + FWD in Startelf >8');

        // Gesamt >15
        const total = Object.values(tempZoneCounts).reduce((a, b) => a + b, 0);
        if (total > 15) violations.push('Team zu groß (max 15)');

        // Club max 3 (aktuell +1, minus replaced falls aus gleichem Club)
        let clubCount = Object.values(squad).flat().filter(p => p.teamId == player.teamId).length + 1;
        if (replaceId) {
            const replacedPlayer = Object.values(squad).flat().find(p => p.id == replaceId);
            if (replacedPlayer && replacedPlayer.teamId == player.teamId) clubCount--;
        }
        if (clubCount > 3) violations.push(`Zu viele vom Club ${player.club} (max 3)`);

        if (violations.length > 0) return { valid: false, reason: violations.join('; ') };

        return { valid: true };
    }

    // Erweiterte Swap-Validierung mit voller Simulation (beide removes + adds)
    function isValidSwap(dropPlayer, dropKey, targetPlayer, targetKey) {
        // Position-Checks (einfach)
        const targetZoneKey = targetKey; // Zielzone für dropPlayer
        const dropZoneKey = dropKey; // Alte Zone für targetPlayer (Ziel für target)

        const posViolations = [];
        if (targetZoneKey === 'gk' && dropPlayer.pos !== 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');
        if (targetZoneKey === 'def' && dropPlayer.pos !== 'DEF') posViolations.push('Drop-Pos passt nicht zur Zielzone');
        if (targetZoneKey === 'mid' && dropPlayer.pos !== 'MID') posViolations.push('Drop-Pos passt nicht zur Zielzone');
        if (targetZoneKey === 'fwd' && dropPlayer.pos !== 'FWD') posViolations.push('Drop-Pos passt nicht zur Zielzone');
        if (targetZoneKey === 'bench-gk' && dropPlayer.pos !== 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');
        if (targetZoneKey === 'bench-field' && dropPlayer.pos === 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');

        if (dropZoneKey === 'gk' && targetPlayer.pos !== 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');
        if (dropZoneKey === 'def' && targetPlayer.pos !== 'DEF') posViolations.push('Target-Pos passt nicht zur alten Zone');
        if (dropZoneKey === 'mid' && targetPlayer.pos !== 'MID') posViolations.push('Target-Pos passt nicht zur alten Zone');
        if (dropZoneKey === 'fwd' && targetPlayer.pos !== 'FWD') posViolations.push('Target-Pos passt nicht zur alten Zone');
        if (dropZoneKey === 'bench-gk' && targetPlayer.pos !== 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');
        if (dropZoneKey === 'bench-field' && targetPlayer.pos === 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');

        if (posViolations.length > 0) return { valid: false, reason: posViolations.join('; ') };

        // Simuliere vollen Swap: Aktuelle Counts, subtrahiere beide, addiere umgekehrt
        const counts = getZoneCounts();
        const posCounts = getPosCounts();
        const tempZoneCounts = { ...counts };
        const tempPosCounts = { ...posCounts };

        // Subtrahiere dropPlayer aus dropKey
        if (dropKey === 'gk') { tempZoneCounts.gk--; tempPosCounts.GK--; }
        else if (dropKey === 'def') { tempZoneCounts.def--; tempPosCounts.DEF--; }
        else if (dropKey === 'mid') { tempZoneCounts.mid--; tempPosCounts.MID--; }
        else if (dropKey === 'fwd') { tempZoneCounts.fwd--; tempPosCounts.FWD--; }
        else if (dropKey === 'bench-gk') { tempZoneCounts['bench-gk']--; tempPosCounts.GK--; }
        else if (dropKey === 'bench-field') { 
            tempZoneCounts['bench-field']--; 
            if (dropPlayer.pos === 'DEF') tempPosCounts.DEF--;
            else if (dropPlayer.pos === 'MID') tempPosCounts.MID--;
            else if (dropPlayer.pos === 'FWD') tempPosCounts.FWD--;
        }

        // Subtrahiere targetPlayer aus targetKey
        if (targetKey === 'gk') { tempZoneCounts.gk--; tempPosCounts.GK--; }
        else if (targetKey === 'def') { tempZoneCounts.def--; tempPosCounts.DEF--; }
        else if (targetKey === 'mid') { tempZoneCounts.mid--; tempPosCounts.MID--; }
        else if (targetKey === 'fwd') { tempZoneCounts.fwd--; tempPosCounts.FWD--; }
        else if (targetKey === 'bench-gk') { tempZoneCounts['bench-gk']--; tempPosCounts.GK--; }
        else if (targetKey === 'bench-field') { 
            tempZoneCounts['bench-field']--; 
            if (targetPlayer.pos === 'DEF') tempPosCounts.DEF--;
            else if (targetPlayer.pos === 'MID') tempPosCounts.MID--;
            else if (targetPlayer.pos === 'FWD') tempPosCounts.FWD--;
        }

        // Add dropPlayer zu targetKey
        if (targetKey === 'gk') { tempZoneCounts.gk++; tempPosCounts.GK++; }
        else if (targetKey === 'def') { tempZoneCounts.def++; tempPosCounts.DEF++; }
        else if (targetKey === 'mid') { tempZoneCounts.mid++; tempPosCounts.MID++; }
        else if (targetKey === 'fwd') { tempZoneCounts.fwd++; tempPosCounts.FWD++; }
        else if (targetKey === 'bench-gk') { tempZoneCounts['bench-gk']++; tempPosCounts.GK++; }
        else if (targetKey === 'bench-field') {
            tempZoneCounts['bench-field']++;
            if (dropPlayer.pos === 'DEF') tempPosCounts.DEF++;
            else if (dropPlayer.pos === 'MID') tempPosCounts.MID++;
            else if (dropPlayer.pos === 'FWD') tempPosCounts.FWD++;
        }

        // Add targetPlayer zu dropKey
        if (dropKey === 'gk') { tempZoneCounts.gk++; tempPosCounts.GK++; }
        else if (dropKey === 'def') { tempZoneCounts.def++; tempPosCounts.DEF++; }
        else if (dropKey === 'mid') { tempZoneCounts.mid++; tempPosCounts.MID++; }
        else if (dropKey === 'fwd') { tempZoneCounts.fwd++; tempPosCounts.FWD++; }
        else if (dropKey === 'bench-gk') { tempZoneCounts['bench-gk']++; tempPosCounts.GK++; }
        else if (dropKey === 'bench-field') {
            tempZoneCounts['bench-field']++;
            if (targetPlayer.pos === 'DEF') tempPosCounts.DEF++;
            else if (targetPlayer.pos === 'MID') tempPosCounts.MID++;
            else if (targetPlayer.pos === 'FWD') tempPosCounts.FWD++;
        }

        // Nun alle Regeln prüfen (wie in isValidDrop)
        const violations = [];

        // Zone-Kapazitäten
        const zoneMax = { gk:1, def:5, mid:5, fwd:3, 'bench-gk':1, 'bench-field':3 };
        if (tempZoneCounts.gk > zoneMax.gk) violations.push(`GK Zone voll (${tempZoneCounts.gk}/1)`);
        if (tempZoneCounts.def > zoneMax.def) violations.push(`DEF Zone voll (${tempZoneCounts.def}/5)`);
        if (tempZoneCounts.mid > zoneMax.mid) violations.push(`MID Zone voll (${tempZoneCounts.mid}/5)`);
        if (tempZoneCounts.fwd > zoneMax.fwd) violations.push(`FWD Zone voll (${tempZoneCounts.fwd}/3)`);
        if (tempZoneCounts['bench-gk'] > zoneMax['bench-gk']) violations.push(`Bench-GK Zone voll (${tempZoneCounts['bench-gk']}/1)`);
        if (tempZoneCounts['bench-field'] > zoneMax['bench-field']) violations.push(`Bench-Field Zone voll (${tempZoneCounts['bench-field']}/3)`);

        // Startelf max 11
        const tempStart11 = tempZoneCounts.gk + tempZoneCounts.def + tempZoneCounts.mid + tempZoneCounts.fwd;
        if (tempStart11 > 11) violations.push('Startelf voll (max 11)');

        // Max pro Position
        if (tempPosCounts.GK > 2) violations.push('Zu viele GK (max 2)');
        if (tempPosCounts.DEF > 5) violations.push('Zu viele DEF (max 5)');
        if (tempPosCounts.MID > 5) violations.push('Zu viele MID (max 5)');
        if (tempPosCounts.FWD > 3) violations.push('Zu viele FWD (max 3)');

        // Startelf-Totals
        if (tempZoneCounts.def + tempZoneCounts.mid > 9) violations.push('Start DEF+MID >9');
        if (tempZoneCounts.def + tempZoneCounts.fwd > 8) violations.push('Start DEF+FWD >8');
        if (tempZoneCounts.mid + tempZoneCounts.fwd > 8) violations.push('Start MID+FWD >8');

        // Gesamt >15
        const total = Object.values(tempZoneCounts).reduce((a, b) => a + b, 0);
        if (total > 15) violations.push('Team zu groß (max 15)');

        // Club max 3 (für dropPlayer; target ist schon im Team, aber da wir swap, bleibt gleich)
        let clubCountDrop = Object.values(squad).flat().filter(p => p.teamId == dropPlayer.teamId).length; // +1 -1 = gleich
        let clubCountTarget = Object.values(squad).flat().filter(p => p.teamId == targetPlayer.teamId).length;
        if (clubCountDrop > 3) violations.push(`Zu viele vom Club ${dropPlayer.club} (max 3)`);
        if (clubCountTarget > 3) violations.push(`Zu viele vom Club ${targetPlayer.club} (max 3)`);

        if (violations.length > 0) return { valid: false, reason: violations.join('; ') };

        return { valid: true };
    }

    function addToSquad(zone, player) {
        const key = getZoneKey(zone.id);
        squad[key].push(player);
        squadTotalCost += player.cost; // Kosten addieren

        const div = document.createElement('div');
        const isBench = ['bench-gk', 'bench-field'].includes(key); // NEU: Prüfe für Bank
        div.className = `squad-player ${isBench ? 'bench-squad-player' : ''}`; // NEU: Klasse für Bank
        div.dataset.id = player.id;
        div.draggable = true;
        div.innerHTML = `
            <img src="${player.photo}" alt="" draggable="false">
            <div><div style="font-size:${isBench ? '.7rem' : '.8rem'};font-weight:600;">${player.name}</div>
                 <div style="font-size:${isBench ? '.65rem' : '.7rem'};color:#666;">${player.club}</div></div>
            <div class="pos" style="font-size:${isBench ? '.65rem' : '.75rem'};">${player.pos}</div>
        `;

        // Drag-Events für Squad-Spieler (Verschieben)
        div.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', player.id + '|' + key);
            div.classList.add('dragging');
            setTimeout(() => div.style.opacity = '0.3', 0);
        });
        div.addEventListener('dragend', () => {
            div.classList.remove('dragging');
            div.style.opacity = ''; // Lass CSS übernehmen
        });

        // Ersetzen-Funktion: Mach squad-player zu Drop-Ziel
        div.addEventListener('dragover', e => e.preventDefault());
        div.addEventListener('drop', e => {
            e.preventDefault();
            const data = e.dataTransfer.getData('text/plain');
            let dropId, fromKey;
            if (data.includes('|')) {
                [dropId, fromKey] = data.split('|');
            } else {
                dropId = data;
            }
            if (dropId === player.id) return; // Selbst ersetzen? Ignorieren

            const targetKey = getZoneKey(zone.id); // Zone des Targets
            const targetPlayer = player;

            if (data.includes('|') && fromKey !== targetKey) {
                // Swap-Logik für andere Zonen (z.B. Bank ↔ Start)
                const dropPlayer = allPlayers[dropId];
                if (!dropPlayer) return;
                const swapResult = isValidSwap(dropPlayer, fromKey, targetPlayer, targetKey);
                if (swapResult.valid) {
                    // Swap ausführen: Beide entfernen, dann adden
                    removeFromSquad(dropId); // Entferne Drop aus alter Zone
                    removeFromSquad(player.id); // Entferne Target
                    addToSquad(zone, dropPlayer); // Drop in Target-Zone
                    const zoneMap = { 'gk': 'zone-gk', 'def': 'zone-def', 'mid': 'zone-mid', 'fwd': 'zone-fwd', 'bench-gk': 'zone-bench-gk', 'bench-field': 'zone-bench-field' };
                    const fromZone = document.getElementById(zoneMap[fromKey]);
                    addToSquad(fromZone, targetPlayer); // Target in alte Drop-Zone
                    showToast(`Getauscht: ${targetPlayer.name} ↔ ${dropPlayer.name}`);
                } else {
                    showToast(`Tausch nicht möglich: ${swapResult.reason}`);
                }
            } else {
                // Standard-Ersetzen (gleiche Zone oder von Liste)
                let dropPlayer;
                if (data.includes('|')) {
                    dropPlayer = allPlayers[dropId];
                } else {
                    // Von Liste
                    const src = document.querySelector(`.player-item[data-id="${dropId}"]`);
                    if (!src) return;
                    dropPlayer = {
                        id: src.dataset.id,
                        name: src.dataset.name,
                        pos: src.dataset.pos,
                        club: src.dataset.club,
                        teamId: src.dataset.teamId,
                        photo: src.dataset.photo,
                        cost: parseFloat(src.dataset.cost) || 0
                    };
                }
                if (!dropPlayer) return;
                const result = isValidDrop(zone, dropPlayer, player.id); // Ersetze diesen
                if (result.valid) {
                    removeFromSquad(player.id);
                    addToSquad(zone, dropPlayer);
                    showToast(`Ersetzt: ${player.name} durch ${dropPlayer.name}`);
                } else {
                    showToast(`Ersetzen nicht möglich: ${result.reason}`);
                }
            }
        });

        zone.appendChild(div);
        updateUI();
    }

    function removeFromSquad(id) {
        for (const k in squad) {
            const removed = squad[k].find(p => p.id == id);
            if (removed) {
                squad[k] = squad[k].filter(p => p.id != id);
                squadTotalCost -= removed.cost; // Kosten subtrahieren
            }
        }
        document.querySelector(`.squad-player[data-id="${id}"], .bench-squad-player[data-id="${id}"]`)?.remove(); // NEU: Auch Bank-Klasse
        updateUI();
    }

    function updateUI() {
        const counts = getZoneCounts();
        const posCounts = getPosCounts();
        const total = Object.values(counts).reduce((a, b) => a + b, 0);

        document.getElementById('count-total').textContent = total;

        // Value-Anzeige updaten
        const valueTotalEl = document.getElementById('value-total');
        const valueDiffEl = document.getElementById('value-diff');
        valueTotalEl.textContent = squadTotalCost.toFixed(1);
        const diff = squadTotalCost - 100;
        if (diff > 0) {
            // Über: Rot, negativ
            valueDiffEl.textContent = `-${diff.toFixed(1)}`;
            valueDiffEl.className = 'value-diff over';
        } else if (diff < 0) {
            // Unter: Grün, positiv
            valueDiffEl.textContent = `+${Math.abs(diff).toFixed(1)}`;
            valueDiffEl.className = 'value-diff under';
        } else {
            valueDiffEl.textContent = '';
            valueDiffEl.className = 'value-diff';
        }

        // Labels updaten
        const labels = {
            'zone-gk': 'GK',
            'zone-def': 'DEF',
            'zone-mid': 'MID',
            'zone-fwd': 'FWD',
            'zone-bench-gk': 'GK',
            'zone-bench-field': 'Feld'
        };
        Object.entries(labels).forEach(([zoneId, label]) => {
            const labelEl = document.querySelector(`#${zoneId}`).previousElementSibling;
            if (labelEl) {
                const cur = counts[getZoneKey(zoneId)];
                const max = { gk:1, def:5, mid:5, fwd:3, 'bench-gk':1, 'bench-field':3 }[getZoneKey(zoneId)];
                labelEl.textContent = `${label} (${cur}/${max})`;
            }
        });

        // Drop-Zone Farben (rot nur bei tatsächlicher Überfüllung > max oder Startelf >11)
        document.querySelectorAll('.drop-zone').forEach(zone => {
            const key = getZoneKey(zone.id);
            const cur = counts[key];
            const max = {gk:1, def:5, mid:5, fwd:3, 'bench-gk':1, 'bench-field':3}[key];
            const start11 = counts.gk + counts.def + counts.mid + counts.fwd;
            const overfull = cur > max || (['gk','def','mid','fwd'].includes(key) && start11 > 11);
            zone.style.borderColor = overfull ? '#dc3545' : '#28a745';
            zone.style.background = overfull ? '#ffebee' : '#d4edda';
        });

        // Synchronisiere Transparenz-Klassen für alle Spieler in der Liste
        document.querySelectorAll('.player-item').forEach(item => {
            const id = item.dataset.id;
            if (isInSquad(id)) {
                item.classList.add('in-squad');
            } else {
                item.classList.remove('in-squad');
            }
        });

        // Finale Validierung
        const violations = [];
        if (total !== 15) violations.push(`Gesamt: ${total}/15`);
        if (posCounts.GK !== 2) violations.push(`GK: ${posCounts.GK}/2`);
        if (posCounts.DEF !== 5) violations.push(`DEF: ${posCounts.DEF}/5`);
        if (posCounts.MID !== 5) violations.push(`MID: ${posCounts.MID}/5`);
        if (posCounts.FWD !== 3) violations.push(`FWD: ${posCounts.FWD}/3`);
        if (counts['bench-gk'] !== 1) violations.push(`Ersatz GK: ${counts['bench-gk']}/1`);
        if (counts['bench-field'] !== 3) violations.push(`Ersatz Feld: ${counts['bench-field']}/3`);
        if (counts.gk !== 1) violations.push(`Start-GK: Muss genau 1 sein (aktuell ${counts.gk})`);
        if (counts.def < 3 || counts.def > 5) violations.push(`Start-DEF: ${counts.def} (3-5)`);
        if (counts.mid < 2 || counts.mid > 5) violations.push(`Start-MID: ${counts.mid} (2–5)`);
        if (counts.fwd < 1 || counts.fwd > 3) violations.push(`Start-FWD: ${counts.fwd} (1-3)`);
        if (counts.gk + counts.def + counts.mid + counts.fwd !== 11) violations.push(`Startelf: ${counts.gk + counts.def + counts.mid + counts.fwd}/11`);
        if (counts.def + counts.mid > 9) violations.push('Start DEF+MID >9');
        if (counts.def + counts.fwd > 8) violations.push('Start DEF+FWD >8');
        if (counts.mid + counts.fwd > 8) violations.push('Start MID+FWD >8');

        const valid = violations.length === 0;
        document.getElementById('status').textContent = valid 
            ? 'TEAM GÜLTIG!' 
            : `Noch nicht gültig: ${violations.join('; ')}`;
        document.getElementById('status').className = `counter ${valid ? 'valid' : 'invalid'}`;

        // Speichern, wenn neu valid
        if (valid && !wasValid) {
            const gw = document.getElementById('gw-select').value;
            saveTeam(gw);
        }
        wasValid = valid;
    }

    // ========== DRAG & DROP ==========
    document.querySelectorAll('.player-item').forEach(item => {
        const playerId = item.dataset.id;
        if (allPlayers[playerId]) {
            item.dataset.cost = allPlayers[playerId].cost;
        }
        item.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', item.dataset.id);
            item.classList.add('dragging');
            setTimeout(() => item.style.opacity = '0.3', 0);
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            item.style.opacity = ''; // Lass CSS übernehmen
        });
    });

    document.querySelectorAll('.drop-zone').forEach(zone => {
        zone.addEventListener('dragover', e => e.preventDefault());
        zone.addEventListener('dragenter', e => {
            e.preventDefault();
            const data = e.dataTransfer.getData('text/plain');
            let id, fromKey, player;
            if (data.includes('|')) {
                [id, fromKey] = data.split('|');
                player = allPlayers[id];
            } else {
                id = data;
                const src = document.querySelector(`.player-item[data-id="${id}"]`);
                if (!src) return;
                player = {
                    id: src.dataset.id,
                    name: src.dataset.name,
                    pos: src.dataset.pos,
                    club: src.dataset.club,
                    teamId: src.dataset.teamId,
                    photo: src.dataset.photo,
                    cost: parseFloat(src.dataset.cost) || 0
                };
            }
            if (!player) return;
            const result = isValidDrop(zone, player);
            zone.classList.toggle('over', result.valid);
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('over');
            const data = e.dataTransfer.getData('text/plain');
            let id, fromKey, player;
            if (data.includes('|')) {
                [id, fromKey] = data.split('|');
                player = allPlayers[id];
                if (!player) return;
                // Verschieben innerhalb Squad: remove, dann add
                removeFromSquad(id);
                const result = isValidDrop(zone, player);
                if (result.valid) {
                    addToSquad(zone, player);
                } else {
                    showToast(`Verschieben nicht möglich: ${result.reason}`);
                    // Bei Fehlschlag: Alten Zustand wiederherstellen
                    const zoneMap = { 'gk': 'zone-gk', 'def': 'zone-def', 'mid': 'zone-mid', 'fwd': 'zone-fwd', 'bench-gk': 'zone-bench-gk', 'bench-field': 'zone-bench-field' };
                    const oldZone = document.getElementById(zoneMap[fromKey]);
                    if (oldZone) addToSquad(oldZone, player);
                }
                return;
            } else {
                id = data;
            }
            const src = document.querySelector(`.player-item[data-id="${id}"]`);
            const squadSrc = document.querySelector(`.squad-player[data-id="${id}"], .bench-squad-player[data-id="${id}"]`); // NEU: Auch Bank
            if (squadSrc) { showToast('Bereits im Team!'); return; }
            if (!src) return;
            player = {
                id: src.dataset.id,
                name: src.dataset.name,
                pos: src.dataset.pos,
                club: src.dataset.club,
                teamId: src.dataset.teamId,
                photo: src.dataset.photo,
                cost: parseFloat(src.dataset.cost) || 0
            };
            const result = isValidDrop(zone, player);
            if (result.valid) {
                addToSquad(zone, player);
            } else {
                showToast(`Nicht erlaubt: ${result.reason}`);
            }
        });
    });

    // Drop außerhalb: Entfernen (nur für Squad-Spieler)
    document.addEventListener('dragover', e => e.preventDefault());
    document.addEventListener('drop', e => {
        if (e.target.closest('.drop-zone') || e.target.closest('.squad-player')) return;
        e.preventDefault();
        const data = e.dataTransfer.getData('text/plain');
        let id, fromKey;
        if (data.includes('|')) {
            [id, fromKey] = data.split('|');
            removeFromSquad(id);
        }
    });

    // Suche
    document.getElementById('search').addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.player-item').forEach(it => {
            const n = it.dataset.name.toLowerCase();
            const c = it.dataset.club.toLowerCase();
            it.style.display = (n.includes(q) || c.includes(q)) ? 'flex' : 'none';
        });
    });

    // GW-Select Event
    document.getElementById('gw-select').addEventListener('change', e => {
        const gw = e.target.value;
        clearSquad();
        loadTeamFromServer(gw);
    });

    // START: Lade Team
    window.addEventListener('load', () => {
        document.getElementById('gw-select').value = defaultGw;
        loadTeamFromServer(defaultGw);
    });
</script>
</body>
</html>