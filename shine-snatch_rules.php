<?php
$file = 'game_data.json';
$data = json_decode(file_get_contents($file), true);

// Hilfs-Array für die Anzeige der Namen (ID -> Name)
$idToName = [];
foreach ($data['cardTypes'] as $ct) {
    $idToName[$ct['id']] = $ct['name'];
}

// Karten nach startId sortieren (Aufsteigend)
usort($data['cardTypes'], function($a, $b) {
    return $a['startId'] <=> $b['startId'];
});
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">   
    <title>Shine-Snatch Regelwerk (Dark Mode)</title>
    <style>
        /* Dark Theme Basis */
        body { 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            padding: 30px; 
            line-height: 1.6; 
            color: #e0e0e0; 
            background-color: #121212; /* Sehr dunkles Grau */
        }

        .container { 
            max-width: 900px; 
            margin: auto; 
            background: #1e1e1e; /* Etwas helleres Grau für den Content */
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.5); 
            border: 1px solid #333;
        }
        
        h1, h2 { 
            color: #ffffff; 
            border-bottom: 2px solid #333; 
            padding-bottom: 10px; 
            margin-top: 30px;
        }

        h1 { color: #bb86fc; } /* Akzentfarbe für den Haupttitel */

        p { margin-bottom: 15px; color: #b0b0b0; }

        /* Tabellen Styling */
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin: 20px 0; 
            font-size: 0.95em; 
            background-color: #252525;
        }

        th, td { 
            border: 1px solid #444; 
            padding: 12px; 
            text-align: left; 
        }

        th { 
            background-color: #333; 
            color: #bb86fc; 
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 1px;
        }

        /* Farbkategorien (angepasst für Dark Mode) */
        .bg-green  { background: #2e7d32; color: #ffffff; font-weight: bold; }
        .bg-yellow { background: #fbc02d; color: #000000; font-weight: bold; }
        .bg-orange { background: #e67e22; color: #ffffff; font-weight: bold; }
        .bg-dark-orange { background: #a84310; color: #ffffff; font-weight: bold; }
        .bg-red    { background: #c62828; color: #ffffff; font-weight: bold; }
        
        .total-row { 
            background: #2c2c2c; 
            font-weight: bold; 
            color: #ffffff;
        }

        .highlight { 
            color: #cf6679; 
            font-weight: bold; 
        }

        strong { color: #ffffff; }

        /* Hover-Effekt für Tabellenzeilen */
        tbody tr:hover {
            background-color: #2a2a2a;
        }

        th {
    cursor: pointer;
    position: relative;
    transition: background 0.3s;
}

th:hover {
    background: rgba(44, 178, 76, 0.2);
}

/* Kleiner Pfeil-Indikator */
th::after {
    content: ' ↕';
    font-size: 0.7em;
    opacity: 0.3;
}

.btn-reset {
    background-color: #ef4444; /* Ein kräftiges Rot */
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-family: 'Signika', sans-serif;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 15px;
    display: none; /* Standardmäßig ausblenden, wird per JS eingeblendet */
    transition: all 0.2s ease;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
}

.btn-reset:hover {
    background-color: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
}

.btn-reset:active {
    transform: translateY(0);
}

/* Markierte Zeile in der Kartenübersicht */
.card-row.selected {
    background-color: rgba(44, 178, 76, 0.3) !important;
    outline: 2px solid var(--primary);
    outline-offset: -2px;
}

/* Ausgeblendete Combos */
.combo-row.hidden {
    display: none;
}

/* Kleiner Hinweis-Badge */
.filter-info {
    margin-top: 10px;
    font-size: 0.9em;
    color: var(--primary);
    display: none; /* Wird per JS eingeblendet */
}

.filter-tag {
    display: inline-block;
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.85em;
    margin-right: 8px;
    margin-bottom: 5px;
    border: 1px solid rgba(255,255,255,0.2);
    animation: fadeIn 0.3s ease;
}

.filter-label {
    color: var(--text);
    font-size: 0.9em;
    margin-right: 10px;
    opacity: 0.7;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

.card-row:hover {
    background-color: rgba(187, 134, 252, 0.1) !important; /* Ganz leichter lila Schimmer beim Hover */
}
    </style>
</head>
<body>
<div class="container">
    <h1>Kartenspiel: Shine-Snatch Regeln</h1>
    <h2>1. Wie man es spielt</h2>
    <p>Jeder Spieler hat ein Deck mit <strong>60 Karten</strong> vor sich liegen. Zu Beginn werden <strong>5 Karten</strong> gezogen.</p>
    <p>Ziel ist es, die höchste Punktzahl zu erreichen. Punkte werden durch den Einzelwert der Karten und durch das Bilden von <strong>Kombinationen</strong> erzielt. Jede Karte darf nur in einer Kombination sein.</p>
    <p><span class="highlight">Sammelkarten Style Punkte:</span> Sehr seltene Karten können zusätzlich <span class="highlight">1d4 Extra-Punkte</span> einbringen.</p>
    
        <h2>2. Gruppen-Definitionen</h2>
    <p><small>Diese Gruppen zählen in Kombinationen als Platzhalter für die enthaltenen Karten.</small></p>
    <table>
        <thead>
            <tr>
                <th>Gruppen-ID</th>
                <th>Enthaltene Karten</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['groups'] as $g): ?>
            <tr>
                <td style="color: var(--accent); font-weight: bold;"><?= $g['id'] ?></td>
                <td>
                    <?php 
                    $memberNames = array_map(function($id) use ($idToName) {
                        return $idToName[$id] ?? $id;
                    }, $g['cards']);
                    echo implode(", ", $memberNames);
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>3. Kartenübersicht <span style="font-size: 0.5em; opacity: 0.6; font-weight: normal; margin-left: 10px; vertical-align: middle;">(Zeile anklicken für Filterauswahl)</span></h2>
    <table>
        <thead>
            <tr>
                <th>Kategorien</th>
                <th data-type="number">Anzahl</th>
                <th data-type="number">Würfelbereich</th>
                <th data-type="number">Wert</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalCards = 0;
            foreach ($data['cardTypes'] as $ct): 
                $totalCards += $ct['count'];
                $rangeEnd = $ct['startId'] + $ct['count'] - 1;
                
                // Farbcodes basierend auf Punkten
                $bgClass = "bg-red";
                if ($ct['points'] >= 30) $bgClass = "bg-green";
                elseif ($ct['points'] >= 20) $bgClass = "bg-yellow";
                elseif ($ct['points'] >= 15) $bgClass = "bg-orange";
                elseif ($ct['points'] >= 10) $bgClass = "bg-dark-orange";
            ?>
            <tr class="card-row" data-id="<?= $ct['id'] ?>" style="cursor: pointer;">
                <td><?= $ct['emoji'] ?> <?= $ct['name'] ?></td>
                <td><?= $ct['count'] ?></td>
                <td><strong><?= $ct['startId'] ?> - <?= $rangeEnd ?></strong></td>
                <td class="<?= $bgClass ?>"><?= $ct['points'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>



       

    <h2>4. Kombinationen (Siegpunkte)</h2>
    <button id="reset-filter" class="btn-reset">
    Auswahl zurücksetzen
</button>
        <div id="filter-display" style="margin-bottom: 15px; min-height: 30px;">

    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Bedarf</th>
                <th data-type="number">Punkte</th>
                <th>Kat</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['combos'] as $cb): 
                $readableNeeds = array_map(function($id) use ($idToName, $data) {
                    // Prüfen ob es eine Gruppe ist
                    foreach($data['groups'] as $g) {
                        if($g['id'] === $id) return "<b>Gruppe: " . $id . "</b>";
                    }
                    return $idToName[$id] ?? $id;
                }, $cb['needs']);
                
                $counts = array_count_values($readableNeeds);
                $needsString = [];
                foreach ($counts as $name => $count) {
                    $needsString[] = ($count > 1 ? $count . "x " : "") . $name;
                }
            ?>
            <tr class="combo-row" data-needs="<?= implode(',', $cb['needs']) ?>">
                <td><?= $cb['emoji'] ?> <?= $cb['name'] ?></td>
                <td style="font-size: 0.85em; color: #cbd5e1;">
                    <?= implode(", ", $needsString) ?>
                </td>
                <td style="font-weight: bold; color: #46d366;"><?= $cb['points'] ?></td>
                <td><small><?= $cb['cat'] ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
// Globales Mapping für die Gruppen (aus PHP)
const groupMapping = <?= json_encode($data['groups']) ?>;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Elemente referenzieren
    const cardRows = document.querySelectorAll('.card-row');
    const comboRows = document.querySelectorAll('.combo-row');
    const resetButton = document.getElementById('reset-filter');
    const filterDisplay = document.getElementById('filter-display');
    const thElements = document.querySelectorAll('th');
    
    let selectedIds = new Set();

    // --- SORTIER FUNKTION ---
    thElements.forEach(th => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(th.parentNode.children).indexOf(th);
            const type = th.dataset.type || 'string';
            const isAscending = th.classList.contains('sort-asc');

            rows.sort((a, b) => {
                let valA = a.children[index].innerText.trim();
                let valB = b.children[index].innerText.trim();

                if (type === 'number') {
                    let numA = parseFloat(valA.match(/-?\d+(\.\d+)?/)) || 0;
                    let numB = parseFloat(valB.match(/-?\d+(\.\d+)?/)) || 0;
                    return isAscending ? numB - numA : numA - numB;
                } else {
                    let cleanA = valA.replace(/^[^a-zA-Z0-9]+/, '').toLowerCase();
                    let cleanB = valB.replace(/^[^a-zA-Z0-9]+/, '').toLowerCase();
                    if (cleanA === "") cleanA = valA;
                    if (cleanB === "") cleanB = valB;
                    return isAscending ? cleanB.localeCompare(cleanA) : cleanA.localeCompare(cleanB);
                }
            });

            table.querySelectorAll('th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.toggle('sort-asc', !isAscending);
            th.classList.toggle('sort-desc', isAscending);
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // --- FILTER FUNKTION (Jetzt korrekt im Scope) ---
    function updateComboFilter() {
        if (!filterDisplay || !resetButton) return;

        if (selectedIds.size === 0) {
            comboRows.forEach(row => row.classList.remove('hidden'));
            filterDisplay.innerHTML = '';
            resetButton.style.display = 'none';
            return;
        }

        resetButton.style.display = 'inline-block';

        // Namen für Tags sammeln
        let filterNames = [];
        selectedIds.forEach(id => {
            const row = document.querySelector(`.card-row[data-id="${id}"]`);
            if (row) {
                // Emoji + Name extrahieren
                filterNames.push(row.cells[0].innerText.trim());
            }
        });

        // Tags anzeigen (Farbe fixiert, da --primary oft fehlte)
        filterDisplay.innerHTML = '<span class="filter-label">Filter aktiv für:</span>' + 
            filterNames.map(name => `<span class="filter-tag" style="background:#bb86fc; color:#000;">${name}</span>`).join('');

        // Combo Zeilen filtern
        comboRows.forEach(row => {
            const needs = row.dataset.needs.split(',');
            let allSelectedAreIncluded = true;

            selectedIds.forEach(selectedId => {
                let found = needs.includes(selectedId);
                if (!found) {
                    groupMapping.forEach(group => {
                        if (group.cards.includes(selectedId) && needs.includes(group.id)) {
                            found = true;
                        }
                    });
                }
                if (!found) allSelectedAreIncluded = false;
            });

            row.classList.toggle('hidden', !allSelectedAreIncluded);
        });

        // Leeres Ergebnis prüfen
        const visibleCombos = document.querySelectorAll('.combo-row:not(.hidden)').length;
        if (visibleCombos === 0) {
            filterDisplay.innerHTML += `<div style="color:#cf6679; margin-top:5px; font-size:0.9em;">⚠️ Keine exakte Kombination möglich.</div>`;
        }
    }

    // --- EVENT LISTENER ---
    cardRows.forEach(row => {
        row.addEventListener('click', () => {
            const cardId = row.dataset.id;
            if (selectedIds.has(cardId)) {
                selectedIds.delete(cardId);
                row.classList.remove('selected');
            } else {
                selectedIds.add(cardId);
                row.classList.add('selected');
            }
            updateComboFilter();
        });
    });

    resetButton.addEventListener('click', () => {
        selectedIds.clear();
        cardRows.forEach(row => row.classList.remove('selected'));
        updateComboFilter();
    });
});
</script>
</body>
</html>