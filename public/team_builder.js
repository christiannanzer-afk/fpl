// ========== GLOBALS ==========
const squad = { gk: [], def: [], mid: [], fwd: [], 'bench-gk': [], 'bench-field': [] };
let wasValid = false;
let squadTotalCost = 0;
let dreamTeamIds = [];

// ========== UTILITY FUNCTIONS ==========
function getZoneKey(id) {
    const map = {
        'zone-gk': 'gk',
        'zone-def': 'def',
        'zone-mid': 'mid',
        'zone-fwd': 'fwd',
        'zone-bench-gk': 'bench-gk',
        'zone-bench-field': 'bench-field'
    };
    return map[id];
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

// ========== TEAM MANAGEMENT ==========
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

function clearSquad() {
    Object.keys(squad).forEach(key => squad[key] = []);
    squadTotalCost = 0;
    document.querySelectorAll('.drop-zone').forEach(zone => zone.innerHTML = '');
    wasValid = false;
    updateUI();
}

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

function placeDreamTeam(ids) {
    clearSquad();
    if (!Array.isArray(ids) || ids.length !== 15) return;

    const zones = {
        gk: document.getElementById('zone-gk'),
        def: document.getElementById('zone-def'),
        mid: document.getElementById('zone-mid'),
        fwd: document.getElementById('zone-fwd'),
        'bench-gk': document.getElementById('zone-bench-gk'),
        'bench-field': document.getElementById('zone-bench-field')
    };

    let placed = { gk: 0, def: 0, mid: 0, fwd: 0, 'bench-gk': 0, 'bench-field': 0 };
    let placed_start_total = 0;

    ids.forEach(id => {
        const p = allPlayers[id];
        if (!p) return;

        let targetZone = null;
        let key = null;

        if (p.pos === 'GK') {
            if (placed.gk < 1 && placed_start_total < 11) {
                targetZone = zones.gk;
                key = 'gk';
            } else if (placed['bench-gk'] < 1) {
                targetZone = zones['bench-gk'];
                key = 'bench-gk';
            }
        } else if (p.pos === 'DEF') {
            if (placed.def < 5 && placed_start_total < 11) {
                targetZone = zones.def;
                key = 'def';
            } else if (placed['bench-field'] < 3) {
                targetZone = zones['bench-field'];
                key = 'bench-field';
            }
        } else if (p.pos === 'MID') {
            if (placed.mid < 5 && placed_start_total < 11) {
                targetZone = zones.mid;
                key = 'mid';
            } else if (placed['bench-field'] < 3) {
                targetZone = zones['bench-field'];
                key = 'bench-field';
            }
        } else if (p.pos === 'FWD') {
            if (placed.fwd < 3 && placed_start_total < 11) {
                targetZone = zones.fwd;
                key = 'fwd';
            } else if (placed['bench-field'] < 3) {
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

// ========== VALIDATION ==========
function isValidDrop(zone, player, replaceId = null) {
    const key = getZoneKey(zone.id);
    const pos = player.pos;
    const counts = getZoneCounts();
    const posCounts = getPosCounts();
    const violations = [];

    // Position check
    if (key === 'gk' && pos !== 'GK') violations.push('Falsche Position: Nur GK hier');
    if (key === 'def' && pos !== 'DEF') violations.push('Falsche Position: Nur DEF hier');
    if (key === 'mid' && pos !== 'MID') violations.push('Falsche Position: Nur MID hier');
    if (key === 'fwd' && pos !== 'FWD') violations.push('Falsche Position: Nur FWD hier');
    if (key === 'bench-gk' && pos !== 'GK') violations.push('Falsche Position: Nur Ersatz-GK hier');
    if (key === 'bench-field' && pos === 'GK') violations.push('Falsche Position: Kein GK auf Feld-Ersatzbank');

    if (violations.length > 0) return { valid: false, reason: violations.join('; ') };

    // Simulate drop
    let tempZoneCounts = { ...counts };
    let tempPosCounts = { ...posCounts };

    if (replaceId) {
        const replacedPlayer = Object.values(squad).flat().find(p => p.id == replaceId);
        if (replacedPlayer) {
            const replaceKey = Object.keys(squad).find(k => squad[k].some(p => p.id == replaceId));
            if (replaceKey) {
                tempZoneCounts[replaceKey]--;
                if (replacedPlayer.pos === 'GK') tempPosCounts.GK--;
                else if (replacedPlayer.pos === 'DEF') tempPosCounts.DEF--;
                else if (replacedPlayer.pos === 'MID') tempPosCounts.MID--;
                else if (replacedPlayer.pos === 'FWD') tempPosCounts.FWD--;
            }
        }
    }

    tempZoneCounts[key]++;
    if (pos === 'GK') tempPosCounts.GK++;
    else if (pos === 'DEF') tempPosCounts.DEF++;
    else if (pos === 'MID') tempPosCounts.MID++;
    else if (pos === 'FWD') tempPosCounts.FWD++;

    // Zone capacity checks
    const zoneMax = { gk: 1, def: 5, mid: 5, fwd: 3, 'bench-gk': 1, 'bench-field': 3 };
    if (tempZoneCounts.gk > zoneMax.gk) violations.push(`GK Zone voll (${tempZoneCounts.gk}/1)`);
    if (tempZoneCounts.def > zoneMax.def) violations.push(`DEF Zone voll (${tempZoneCounts.def}/5)`);
    if (tempZoneCounts.mid > zoneMax.mid) violations.push(`MID Zone voll (${tempZoneCounts.mid}/5)`);
    if (tempZoneCounts.fwd > zoneMax.fwd) violations.push(`FWD Zone voll (${tempZoneCounts.fwd}/3)`);
    if (tempZoneCounts['bench-gk'] > zoneMax['bench-gk']) violations.push(`Bench-GK Zone voll (${tempZoneCounts['bench-gk']}/1)`);
    if (tempZoneCounts['bench-field'] > zoneMax['bench-field']) violations.push(`Bench-Field Zone voll (${tempZoneCounts['bench-field']}/3)`);

    const tempStart11 = tempZoneCounts.gk + tempZoneCounts.def + tempZoneCounts.mid + tempZoneCounts.fwd;
    if (tempStart11 > 11) violations.push('Startelf voll (max 11)');

    if (tempPosCounts.GK > 2) violations.push('Zu viele GK (max 2)');
    if (tempPosCounts.DEF > 5) violations.push('Zu viele DEF (max 5)');
    if (tempPosCounts.MID > 5) violations.push('Zu viele MID (max 5)');
    if (tempPosCounts.FWD > 3) violations.push('Zu viele FWD (max 3)');

    if (tempZoneCounts.def + tempZoneCounts.mid > 9) violations.push('Start DEF+MID >9');
    if (tempZoneCounts.def + tempZoneCounts.fwd > 8) violations.push('Start DEF+FWD >8');
    if (tempZoneCounts.mid + tempZoneCounts.fwd > 8) violations.push('Start MID+FWD >8');

    const total = Object.values(tempZoneCounts).reduce((a, b) => a + b, 0);
    if (total > 15) violations.push('Team zu groß (max 15)');

    // Club max 3
    const allSquadPlayers = Object.values(squad).flat();
    let testSquad = replaceId ? allSquadPlayers.filter(p => p.id != replaceId) : [...allSquadPlayers];
    testSquad.push(player);
    const clubCount = testSquad.filter(p => p.teamId == player.teamId).length;
    if (clubCount > 3) violations.push(`Zu viele vom Club ${player.club} (max 3)`);

    if (violations.length > 0) return { valid: false, reason: violations.join('; ') };
    return { valid: true };
}

function isValidSwap(dropPlayer, dropKey, targetPlayer, targetKey) {
    const posViolations = [];
    
    // Position checks for target zone
    if (targetKey === 'gk' && dropPlayer.pos !== 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');
    if (targetKey === 'def' && dropPlayer.pos !== 'DEF') posViolations.push('Drop-Pos passt nicht zur Zielzone');
    if (targetKey === 'mid' && dropPlayer.pos !== 'MID') posViolations.push('Drop-Pos passt nicht zur Zielzone');
    if (targetKey === 'fwd' && dropPlayer.pos !== 'FWD') posViolations.push('Drop-Pos passt nicht zur Zielzone');
    if (targetKey === 'bench-gk' && dropPlayer.pos !== 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');
    if (targetKey === 'bench-field' && dropPlayer.pos === 'GK') posViolations.push('Drop-Pos passt nicht zur Zielzone');

    if (dropKey === 'gk' && targetPlayer.pos !== 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');
    if (dropKey === 'def' && targetPlayer.pos !== 'DEF') posViolations.push('Target-Pos passt nicht zur alten Zone');
    if (dropKey === 'mid' && targetPlayer.pos !== 'MID') posViolations.push('Target-Pos passt nicht zur alten Zone');
    if (dropKey === 'fwd' && targetPlayer.pos !== 'FWD') posViolations.push('Target-Pos passt nicht zur alten Zone');
    if (dropKey === 'bench-gk' && targetPlayer.pos !== 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');
    if (dropKey === 'bench-field' && targetPlayer.pos === 'GK') posViolations.push('Target-Pos passt nicht zur alten Zone');

    if (posViolations.length > 0) return { valid: false, reason: posViolations.join('; ') };

    const counts = getZoneCounts();
    const posCounts = getPosCounts();
    const violations = [];

    // Simulate full swap - counts stay the same, just positions swapped
    let tempZoneCounts = { ...counts };
    let tempPosCounts = { ...posCounts };

    // Rules that could be violated
    const tempStart11 = tempZoneCounts.gk + tempZoneCounts.def + tempZoneCounts.mid + tempZoneCounts.fwd;
    if (tempStart11 > 11) violations.push('Startelf voll (max 11)');

    if (tempPosCounts.GK > 2) violations.push('Zu viele GK (max 2)');
    if (tempPosCounts.DEF > 5) violations.push('Zu viele DEF (max 5)');
    if (tempPosCounts.MID > 5) violations.push('Zu viele MID (max 5)');
    if (tempPosCounts.FWD > 3) violations.push('Zu viele FWD (max 3)');

    if (tempZoneCounts.def + tempZoneCounts.mid > 9) violations.push('Start DEF+MID >9');
    if (tempZoneCounts.def + tempZoneCounts.fwd > 8) violations.push('Start DEF+FWD >8');
    if (tempZoneCounts.mid + tempZoneCounts.fwd > 8) violations.push('Start MID+FWD >8');

    const total = Object.values(tempZoneCounts).reduce((a, b) => a + b, 0);
    if (total > 15) violations.push('Team zu groß (max 15)');

    // Club counts stay same during swap
    let clubCountDrop = Object.values(squad).flat().filter(p => p.teamId == dropPlayer.teamId).length;
    let clubCountTarget = Object.values(squad).flat().filter(p => p.teamId == targetPlayer.teamId).length;
    if (clubCountDrop > 3) violations.push(`Zu viele vom Club ${dropPlayer.club} (max 3)`);
    if (clubCountTarget > 3) violations.push(`Zu viele vom Club ${targetPlayer.club} (max 3)`);

    if (violations.length > 0) return { valid: false, reason: violations.join('; ') };
    return { valid: true };
}

// ========== SQUAD OPERATIONS ==========
function addToSquad(zone, player) {
    const key = getZoneKey(zone.id);
    squad[key].push(player);
    squadTotalCost += player.cost;

    const div = document.createElement('div');
    const isBench = ['bench-gk', 'bench-field'].includes(key);
    div.className = `squad-player ${isBench ? 'bench-squad-player' : ''}`;
    div.dataset.id = player.id;
    div.draggable = true;
    div.innerHTML = `
        <img src="${player.photo}" alt="" draggable="false">
        <div><div style="font-size:${isBench ? '.7rem' : '.8rem'};font-weight:600;">${player.name}</div>
             <div style="font-size:${isBench ? '.65rem' : '.7rem'};color:#666;">${player.club}</div></div>
        <div class="pos" style="font-size:${isBench ? '.65rem' : '.75rem'};">${player.pos}</div>
    `;

    // Drag events for squad players
    div.addEventListener('dragstart', e => {
        e.dataTransfer.setData('text/plain', player.id + '|' + key);
        div.classList.add('dragging');
        setTimeout(() => div.style.opacity = '0.3', 0);
    });
    div.addEventListener('dragend', () => {
        div.classList.remove('dragging');
        div.style.opacity = '';
    });

    // Replace function: make squad-player a drop target
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
        if (dropId === player.id) return;

        const targetKey = getZoneKey(zone.id);
        const targetPlayer = player;

        if (data.includes('|') && fromKey !== targetKey) {
            // Swap logic for different zones
            const dropPlayer = allPlayers[dropId];
            if (!dropPlayer) return;
            const swapResult = isValidSwap(dropPlayer, fromKey, targetPlayer, targetKey);
            if (swapResult.valid) {
                removeFromSquad(dropId);
                removeFromSquad(player.id);
                addToSquad(zone, dropPlayer);
                const zoneMap = {
                    'gk': 'zone-gk', 'def': 'zone-def', 'mid': 'zone-mid', 'fwd': 'zone-fwd',
                    'bench-gk': 'zone-bench-gk', 'bench-field': 'zone-bench-field'
                };
                const fromZone = document.getElementById(zoneMap[fromKey]);
                addToSquad(fromZone, targetPlayer);
                showToast(`Getauscht: ${targetPlayer.name} ↔ ${dropPlayer.name}`);
            } else {
                showToast(`Tausch nicht möglich: ${swapResult.reason}`);
            }
        } else {
            // Standard replace (same zone or from list)
            let dropPlayer;
            if (data.includes('|')) {
                dropPlayer = allPlayers[dropId];
            } else {
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
            const result = isValidDrop(zone, dropPlayer, player.id);
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
            squadTotalCost -= removed.cost;
        }
    }
    document.querySelector(`.squad-player[data-id="${id}"], .bench-squad-player[data-id="${id}"]`)?.remove();
    updateUI();
}

// ========== UI UPDATE ==========
function updateUI() {
    const counts = getZoneCounts();
    const posCounts = getPosCounts();
    const total = Object.values(counts).reduce((a, b) => a + b, 0);

    // Value display
    const valueEl = document.querySelector('.value-total');
    const diffEl = document.querySelector('.value-diff');
    if (valueEl) valueEl.textContent = `${squadTotalCost.toFixed(1)}/100.0`;
    
    const diff = squadTotalCost - 100;
    if (diffEl) {
        diffEl.textContent = diff > 0 ? `+${diff.toFixed(1)}` : diff < 0 ? diff.toFixed(1) : '';
        diffEl.className = 'value-diff ' + (diff > 0 ? 'over' : diff < 0 ? 'under' : '');
    }

    // Zone labels
    document.querySelectorAll('.zone-wrapper').forEach(wrapper => {
        const label = wrapper.querySelector('.zone-label');
        const zone = wrapper.querySelector('.drop-zone');
        if (!label || !zone) return;

        const key = getZoneKey(zone.id);
        const count = counts[key] || 0;
        const zoneMax = { gk: 1, def: 5, mid: 5, fwd: 3, 'bench-gk': 1, 'bench-field': 3 };
        const max = zoneMax[key];
        const posName = { gk: 'GK', def: 'DEF', mid: 'MID', fwd: 'FWD', 'bench-gk': 'GK', 'bench-field': 'Feld' }[key];

        label.textContent = `${posName} (${count}/${max})`;
        const overfull = count > max;
        label.style.color = overfull ? '#dc3545' : '#28a745';
        zone.style.background = overfull ? '#ffebee' : '#d4edda';
    });

    // Sync transparency classes for all players in list
    document.querySelectorAll('.player-item').forEach(item => {
        const id = item.dataset.id;
        if (isInSquad(id)) {
            item.classList.add('in-squad');
        } else {
            item.classList.remove('in-squad');
        }
    });

    // Final validation
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
    if (counts.mid < 2 || counts.mid > 5) violations.push(`Start-MID: ${counts.mid} (2-5)`);
    if (counts.fwd < 1 || counts.fwd > 3) violations.push(`Start-FWD: ${counts.fwd} (1-3)`);
    if (counts.gk + counts.def + counts.mid + counts.fwd !== 11) violations.push(`Startelf: ${counts.gk + counts.def + counts.mid + counts.fwd}/11`);
    if (counts.def + counts.mid > 9) violations.push('Start DEF+MID >9');
    if (counts.def + counts.fwd > 8) violations.push('Start DEF+FWD >8');
    if (counts.mid + counts.fwd > 8) violations.push('Start MID+FWD >8');

    const valid = violations.length === 0;
    const statusEl = document.getElementById('status');
    if (statusEl) {
        statusEl.textContent = valid ? 'TEAM GÜLTIG!' : `Noch nicht gültig: ${violations.join('; ')}`;
        statusEl.className = `counter ${valid ? 'valid' : 'invalid'}`;
    }

    // Save if newly valid
    if (valid && !wasValid) {
        const gwSelect = document.getElementById('gw-select');
        if (gwSelect) {
            const gw = gwSelect.value;
            if (gw > 0) saveTeam(gw);
        }
    }
    wasValid = valid;
}

// ========== DRAG & DROP ==========
document.addEventListener('DOMContentLoaded', () => {
    // Player item drag
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
            item.style.opacity = '';
        });
    });

    // Drop zones
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
                removeFromSquad(id);
                const result = isValidDrop(zone, player);
                if (result.valid) {
                    addToSquad(zone, player);
                } else {
                    showToast(`Verschieben nicht möglich: ${result.reason}`);
                    const zoneMap = {
                        'gk': 'zone-gk', 'def': 'zone-def', 'mid': 'zone-mid', 'fwd': 'zone-fwd',
                        'bench-gk': 'zone-bench-gk', 'bench-field': 'zone-bench-field'
                    };
                    const oldZone = document.getElementById(zoneMap[fromKey]);
                    if (oldZone) addToSquad(oldZone, player);
                }
                return;
            } else {
                id = data;
            }
            const src = document.querySelector(`.player-item[data-id="${id}"]`);
            const squadSrc = document.querySelector(`.squad-player[data-id="${id}"], .bench-squad-player[data-id="${id}"]`);
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

    // Drop outside: Remove (only for squad players)
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

    // Search
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('.player-item').forEach(it => {
                const n = it.dataset.name.toLowerCase();
                const c = it.dataset.club.toLowerCase();
                it.style.display = (n.includes(q) || c.includes(q)) ? '' : 'none';
            });
        });
    }

    // GW Select
    const gwSelect = document.getElementById('gw-select');
    if (gwSelect) {
        gwSelect.addEventListener('change', e => {
            const gw = parseInt(e.target.value);
            if (gw === 0) {
                clearSquad();
            } else {
                loadTeamFromServer(gw);
            }
        });

        // Load default GW on page load
        if (defaultGw > 0) {
            gwSelect.value = defaultGw;
            loadTeamFromServer(defaultGw);
        }
    }
});
