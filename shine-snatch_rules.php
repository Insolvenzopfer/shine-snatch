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

/* Zeilen, die für eine Combo mit der aktuellen Auswahl infrage kommen */
.card-row.possible-match {
    background-color: rgba(2, 49, 45, 0.52) !important; /* Dezentes Türkis */
    border-left: 4px solid var(--secondary, #03dac6);
    transition: all 0.2s ease;
}

/* Optional: Ein kleiner Badge oder Text-Hinweis in der Zeile */
.card-row.possible-match td:first-child::after {
    content: ' ✨';
    font-size: 0.8em;
}

.guide-container {
            max-width: 850px;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            border: 1px solid var(--border);
        }

        /* Hero Section */
        .hero {
            text-align: center;
            border-bottom: 2px solid var(--border);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
.tagline {
            font-style: italic;
            color: var(--secondary);
            font-size: 1.2em;
            margin-top: 10px;
        }
        /* Highlights */
        .highlight {
            color: var(--secondary);
            font-weight: bold;
        }
        /* Phasen-Liste */
        .phase {
            display: flex;
            margin-bottom: 20px;
            align-items: flex-start;
        }

        .phase-number {
            background: var(--primary);
            color: #ffffff;
            font-weight: bold;
            min-width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .phase-content strong {
            color: #fff;
            display: block;
            font-size: 1.1em;
        }

    </style>
</head>
<body>
<div class="container">
    <h1>🌌 Shine-Snatch</h1>
        <div class="tagline">Das Gefüge der Sphären</div>
    </div>

    <section>
        <h2>1. Die Geschichte</h2>
        <p>
            In der Welt von <strong>Shine-Snatch</strong> bist du ein "Sphären-Wanderer". Dein Ziel ist es, aus dem Chaos des Äthers die mächtigsten Wesen, Orte und Artefakte zu manifestieren. 
            Doch eine einzelne Karte ist nur ein Funken im Dunkeln. Erst wenn du Synergien zwischen den Elementen erkennst und Kombinationen bildest, entfesselst du das volle Potenzial deines Decks.
        </p>
        <p>
            Wirst du eine unbezwingbare Horde anführen, ein heiliges Imperium errichten oder die Geheimnisse der arkanen Wissenschaft entschlüsseln?
        </p>
    </section>

    <section>
        <h2>1.1 Das Ziel des Spiels</h2>
        <p>
            Sammle durch geschicktes Kombinieren deiner Handkarten die höchste Punktzahl. Das Spiel belohnt nicht nur den reinen Wert der Karten, sondern vor allem dein Auge für <span class="highlight">Kombinationen (Combos)</span>.
        </p>
    </section>

    <section>
        <h2>1.2 Spielvorbereitung</h2>
        <ul>
            <li>Jeder Spieler verfügt über ein Deck aus <strong>60 Karten</strong>.</li>
            <li>Zu Beginn einer Runde ziehst du <strong>5 Karten</strong> aus deinem Deck.</li>
            <li>Die Karten haben unterschiedliche Seltenheiten und Punktwerte.</li>
        </ul>
    </section>

    <section>
        <h2>1.3 📜 Spielablauf & Wertung</h2>
        
        <div class="phase">
            <div class="phase-number">A</div>
            <div class="phase-content">
                <strong>Der Basiswert</strong>
                Die Summe der auf den Karten aufgedruckten Punkte bildet dein Fundament.
            </div>
        </div>

        <div class="phase">
            <div class="phase-number">B</div>
            <div class="phase-content">
                <strong>Der Würfel-Bonus</strong>
                Besitzt du eine Karte als physische oder markierte "Sammelkarte", darfst du einen <span class="highlight">1d4 Extra-Punkte</span> werfen.
            </div>
        </div>

        <div class="phase">
            <div class="phase-number">C</div>
            <div class="phase-content">
                <strong>Die Synergie-Phase</strong>
                Das System berechnet automatisch die optimale Wertung deiner Kombinationen. 
                <em>Wichtig: Jede Karte kann nur für EINE Kombination verwendet werden.</em>
            </div>
        </div>
    </section>

    <section>
        <div class="info-box">
            <h3>💡 Die Gruppen-Regel</h3>
            <p>
                Einige Kombinationen verlangen keine spezifische Karte, sondern eine <strong>Gruppe</strong> (z. B. KRONE oder ABENTEURER). 
            </p>
        </div>
    </section>

    
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

    // --- SORTIER FUNKTION (Dein Original) ---
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

    // --- FILTER FUNKTION (Mit neuer Highlight-Logik) ---
    function updateComboFilter() {
        if (!filterDisplay || !resetButton) return;

        // Reset: Alle "Möglich"-Klassen entfernen
        cardRows.forEach(row => row.classList.remove('possible-match'));

        if (selectedIds.size === 0) {
            comboRows.forEach(row => row.classList.remove('hidden'));
            filterDisplay.innerHTML = '';
            resetButton.style.display = 'none';
            return;
        }

        resetButton.style.display = 'inline-block';

        let filterNames = [];
        let possiblePartnerIds = new Set(); // Hier sammeln wir IDs, die Combos vervollständigen könnten

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

            if (allSelectedAreIncluded) {
                row.classList.remove('hidden');
                // NEU: Wenn Combo passt, merke dir alle benötigten Karten dieser Combo
                needs.forEach(reqId => {
                    const group = groupMapping.find(g => g.id === reqId);
                    if (group) {
                        group.cards.forEach(cId => possiblePartnerIds.add(cId));
                    } else {
                        possiblePartnerIds.add(reqId);
                    }
                });
            } else {
                row.classList.add('hidden');
            }
        });

        // Partner-Karten in der Übersicht markieren
        cardRows.forEach(row => {
            const rowId = row.dataset.id;
            if (!selectedIds.has(rowId) && possiblePartnerIds.has(rowId)) {
                row.classList.add('possible-match');
            }
        });

        // Tags anzeigen
        selectedIds.forEach(id => {
            const row = document.querySelector(`.card-row[data-id="${id}"]`);
            if (row) filterNames.push(row.cells[0].innerText.trim());
        });

        filterDisplay.innerHTML = '<span class="filter-label">Filter aktiv für:</span>' + 
            filterNames.map(name => `<span class="filter-tag" style="background:#bb86fc; color:#000;">${name}</span>`).join('');

        const visibleCombos = document.querySelectorAll('.combo-row:not(.hidden)').length;
        if (visibleCombos === 0) {
            filterDisplay.innerHTML += `<div style="color:#cf6679; margin-top:5px; font-size:0.9em;">⚠️ Keine exakte Kombination möglich.</div>`;
        }
    }

    // --- EVENT LISTENER (Dein Original) ---
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
        cardRows.forEach(row => {
            row.classList.remove('selected');
            row.classList.remove('possible-match');
        });
        updateComboFilter();
    });
});
</script>
</body>
</html>