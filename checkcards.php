<?php
/**
 * Snatch Balance - Wahrscheinlichkeits-Rechner (SQL-Version mit Gruppen-Support)
 */
require_once "db.php";
$pdo = getDatabaseConnection();

// 1. Kartentypen aus der Datenbank laden
$stmtCards = $pdo->query(
    "SELECT id, emoji, name, count, points, start_id AS startId FROM snatch_game_card_types ORDER BY id ASC",
);
$cardTypes = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

// 2. Kombinationen/Synergien aus der Datenbank laden
$stmtCombos = $pdo->query(
    "SELECT emoji, name, points, needs, cat FROM snatch_game_combos ORDER BY id ASC",
);
$dbCombos = $stmtCombos->fetchAll(PDO::FETCH_ASSOC);

// 3. NEU: Kartengruppen aus der Datenbank laden
$stmtGroups = $pdo->query("SELECT id, cards FROM snatch_game_groups");
$dbGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

// Gruppen-Array für JavaScript vorbereiten (ID => Array von Karten)
$groups = [];
foreach ($dbGroups as $g) {
    $decodedCards = !empty($g["cards"]) ? json_decode($g["cards"], true) : [];
    $groups[strtoupper(trim($g["id"]))] = is_array($decodedCards)
        ? $decodedCards
        : [];
}

// 'needs' aus dem SQL-JSON-Array in ein echtes PHP-Array umwandeln
$combos = [];
foreach ($dbCombos as $cb) {
    $decodedNeeds = !empty($cb["needs"]) ? json_decode($cb["needs"], true) : [];
    $cb["needs"] = is_array($decodedNeeds) ? $decodedNeeds : [];
    $combos[] = $cb;
}

// Gesamtanzahl aller Karten im Pool berechnen
$totalCardsPool = 0;
foreach ($cardTypes as $ct) {
    $totalCardsPool += (int) $ct["count"];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Snatch Balance - 5 Card Mode</title>
    <style>
        body { background: #050705; color: #e2e8f0; font-family: 'Signika', sans-serif; padding: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: rgba(20, 25, 20, 0.7); padding: 20px; border-radius: 15px; border: 1px solid #2cb24c; backdrop-filter: blur(10px); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: #2cb24c; font-size: 0.8rem; text-transform: uppercase; }
        .prob-tag { padding: 4px 8px; border-radius: 5px; font-weight: bold; font-size: 0.85rem; }
        .rare { background: rgba(239, 68, 68, 0.2); color: #ef4444; }   /* < 0.1% */
        .common { background: rgba(16, 185, 129, 0.2); color: #10b981; } /* > 2% */
        .highlight { color: #a855f7; font-weight: bold; }
        h1 { color: #2cb24c; margin-top: 0; }
        .stats-bar { background: rgba(255,255,255,0.02); padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; border-left: 3px solid #a855f7; }
    </style>
</head>
<body>

    <h1>📊 Snatch Core Balance-Check</h1>

    <div class="stats-bar">
        📦 Totaler Karten-Pool in der Datenbank: <strong><?= $totalCardsPool ?> Karten</strong> | 🃏 Hand-Größe: <strong>5 Karten</strong>
    </div>

    <div class="grid">
        <div class="card">
            <h2>🃏 Aktueller Karten-Pool</h2>
            <table>
                <thead>
                    <tr><th>Typ</th><th>Name</th><th>Anzahl im Deck</th><th>Anteil %</th></tr>
                </thead>
                <tbody id="cardsBody">
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>✨ Synergie-Wahrscheinlichkeiten</h2>
            <table>
                <thead>
                    <tr><th>Kombination</th><th>Punkte</th><th>Chance (Hand)</th><th>Effizienz-Index</th></tr>
                </thead>
                <tbody id="comboBody">
                </tbody>
            </table>
        </div>
    </div>

    <script>
    const cardTypes = <?= json_encode($cardTypes) ?>;
    const combos = <?= json_encode($combos) ?>;
    const groups = <?= json_encode($groups) ?>; // NEU: Gruppen an JS übergeben

    const HAND_SIZE = 5;

    function nCr(n, r) {
        if (r < 0 || r > n) return 0;
        if (r === 0 || r === n) return 1;
        if (r > n / 2) r = n - r;
        let res = 1;
        for (let i = 1; i <= r; i++) {
            res = res * (n - i + 1) / i;
        }
        return Math.round(res);
    }

    document.addEventListener("DOMContentLoaded", () => {
        const cardsBody = document.getElementById("cardsBody");
        const comboBody = document.getElementById("comboBody");

        let totalCards = 0;
        let cardMap = {};

        cardTypes.forEach(c => {
            totalCards += parseInt(c.count);
            const uppercaseId = String(c.id).toUpperCase().trim();
            cardMap[uppercaseId] = c;
        });

        const totalCombinations = nCr(totalCards, HAND_SIZE);

        cardTypes.forEach(c => {
            let pct = totalCards > 0 ? ((c.count / totalCards) * 100).toFixed(1) : 0;
            cardsBody.innerHTML += `
                <tr>
                    <td><code>${c.id}</code> ${c.emoji}</td>
                    <td>${c.name}</td>
                    <td><strong>${c.count}x</strong></td>
                    <td style="opacity:0.7">${pct}%</td>
                </tr>`;
        });

        combos.forEach(cb => {
            let finalProb = 0;

            if (cb.needs.length <= HAND_SIZE && totalCombinations > 0) {
                let requirements = {};

                cb.needs.forEach(id => {
                    const uppercaseId = String(id).toUpperCase().trim();
                    requirements[uppercaseId] = (requirements[uppercaseId] || 0) + 1;
                });

                let favorableWays = 1;
                let totalRequiredCards = 0;
                let isValid = true;
                let subPoolsExcluded = 0;

                for (let id in requirements) {
                    let requiredCount = requirements[id];
                    let availableCount = 0;

                    // NEU: Prüfung ob es sich um eine Gruppe handelt (ID-Länge > 3)
                    if (id.length > 3 && groups[id]) {
                        // Addiere die Anzahl aller Karten, die zu dieser Gruppe gehören
                        groups[id].forEach(cardId => {
                            const upperCardId = String(cardId).toUpperCase().trim();
                            if (cardMap[upperCardId]) {
                                availableCount += parseInt(cardMap[upperCardId].count);
                            }
                        });
                        subPoolsExcluded += availableCount;
                    } else {
                        // Regulärer Kartentyp
                        availableCount = cardMap[id] ? parseInt(cardMap[id].count) : 0;
                        subPoolsExcluded += availableCount;
                    }

                    if (availableCount >= requiredCount) {
                        favorableWays *= nCr(availableCount, requiredCount);
                        totalRequiredCards += requiredCount;
                    } else {
                        isValid = false;
                        break;
                    }
                }

                if (isValid) {
                    let remainingHandSlots = HAND_SIZE - totalRequiredCards;
                    let remainingPool = totalCards - subPoolsExcluded;

                    favorableWays *= nCr(remainingPool, remainingHandSlots);
                    finalProb = (favorableWays / totalCombinations) * 100;
                }
            }

            const efficiency = (finalProb > 0) ? (cb.points * finalProb).toFixed(0) : 0;

            let probClass = "prob-tag";
            if (finalProb < 0.1) {
                probClass += " rare";
            } else if (finalProb > 2) {
                probClass += " common";
            }

            comboBody.innerHTML += `
                <tr>
                    <td>${cb.emoji || '✨'} ${cb.name}</td>
                    <td class="highlight">${cb.points}</td>
                    <td><span class="${probClass}">${finalProb.toFixed(4)}%</span></td>
                    <td><strong>${efficiency}</strong></td>
                </tr>`;
        });
    });
    </script>
</body>
</html>
