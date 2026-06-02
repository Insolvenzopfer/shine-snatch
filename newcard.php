<?php
header("Content-Type: text/html; charset=utf-8");

// 1. Verbindung laden
require_once "db.php";
$pdo = getDatabaseConnection();

// ==========================================================
// MATHEMATISCHE BERECHNUNG DES GEWICHTETEN POOLS (Mit Gewichtung)
// ==========================================================
$totalWeightWithBias = 0;
$idWeightsWithBias = [];

for ($i = 1; $i <= 60; $i++) {
    $weight = 61 - $i;
    $idWeightsWithBias[$i] = $weight;
    $totalWeightWithBias += $weight;
}

// ==========================================================
// KATEGORIEN AUS DER DATENBANK LADEN
// ==========================================================
$stmt = $pdo->query(
    "SELECT * FROM snatch_game_card_types ORDER BY start_id ASC",
);
$cardTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Wahrscheinlichkeitsanalyse</title>
    <style>
        body {
            background: #121212;
            color: #e0e0e0;
            font-family: 'Georgia', serif;
            padding: 20px;
        }
        h2 {
            color: #d35400;
            border-bottom: 2px solid #3a2f1d;
            padding-bottom: 10px;
            font-weight: normal;
        }
        p {
            font-size: 15px;
            color: #b0b0b0;
            line-height: 1.6;
        }
        strong {
            color: #fff;
        }

        /* Tabellen-Styling */
        table {
            border-collapse: collapse;
            font-family: sans-serif;
            min-width: 1000px;
            background: #1e1e1e;
            border: 1px solid #3a2f1d;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            margin-top: 20px;
        }
        th, td {
            padding: 10px 12px;
            border: 1px solid #333;
        }

        /* Header-Zeilen */
        tr.main-header {
            background-color: #252525;
            color: #e0e0e0;
        }
        tr.sub-header {
            background-color: #1a1a1a;
            color: #aaa;
            font-size: 13px;
        }

        /* Daten-Zeilen Hover-Effekt */
        tr.data-row:hover {
            background-color: #252525 !important;
        }

        /* Farbliche Trennung der Bereiche */
        .col-bias-title { background-color: #7e3d17 !important; color: white; }
        .col-nobias-title { background-color: #1e5335 !important; color: white; }

        /* Zellen-Klassen für Gewichtung (Orange Nuancen) */
        .cell-bias-main { background-color: #241911; font-weight: bold; color: #e67e22; }
        .cell-bias-sub { background-color: #241911; color: #ba4a00; }

        /* Zellen-Klassen für Gleichverteilung (Grüne Nuancen) */
        .cell-nobias-main { background-color: #131f18; font-weight: bold; color: #2ecc71; }
        .cell-nobias-sub { background-color: #131f18; color: #27ae60; }

        .text-muted { color: #888; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

<?php
echo "<h2>🃏 Snatch-Kartensystem: Wahrscheinlichkeitsanalyse (Vergleich)</h2>";
echo "<p>Pool-Größe mit Gewichtung: <strong>" .
    $totalWeightWithBias .
    " Anteile</strong> (Summe 1+2+3+......+59+60)</p>";
echo "<p>Pool-Größe ohne Gewichtung (Gleichverteilung): <strong>60 Anteile</strong> (jede ID von 1-60 zählt genau 1-mal)</p>";

echo "<table>";
// Header
echo "<tr class='main-header'>
        <th>Kategorie</th>
        <th>ID-Bereich</th>
        <th>Anzahl</th>
        <th colspan='3' class='col-bias-title text-center'>MIT GEWICHTUNG (1830 Anteile)</th>
        <th colspan='2' class='col-nobias-title text-center'>OHNE GEWICHTUNG (60 Anteile)</th>
      </tr>";
// Sub-Header
echo "<tr class='sub-header'>
        <th></th><th></th><th></th>
        <th class='text-center'>Anteile (Kat. / Karte)</th><th class='text-right'>Chance Kat.</th><th class='text-right'>Chance pro ID</th>
        <th class='text-right'>Chance Kat.</th><th class='text-right'>Chance pro ID</th>
      </tr>";

foreach ($cardTypes as $type) {
    $catName = $type["name"];
    $startId = (int) $type["start_id"];
    $count = (int) $type["count"];

    // Berechnungen Gewichtung (1830er Pool)
    $catWeight = 0;
    for ($i = $startId; $i <= $startId + $count - 1; $i++) {
        $catWeight += 61 - $i;
    }
    $weightPerCard = $count > 0 ? $catWeight / $count : 0;
    $catPercentageWithBias = ($catWeight / $totalWeightWithBias) * 100;
    $singleIdPercentageWithBias =
        $count > 0 ? $catPercentageWithBias / $count : 0;

    // Berechnungen Ohne Gewichtung (60er Pool)
    $catPercentageNoBias = ($count / 60) * 100;
    $singleIdPercentageNoBias = $count > 0 ? $catPercentageNoBias / $count : 0;

    echo "<tr class='data-row'>";
    echo "<td>" . $type["emoji"] . " " . $catName . "</td>";
    echo "<td class='text-center'>" .
        $startId .
        " - " .
        ($startId + $count - 1) .
        "</td>";
    echo "<td class='text-center'>" . $count . "</td>";

    // === MIT GEWICHTUNG (3 Spalten) ===
    echo "<td class='text-center cell-bias-main'>" .
        $catWeight .
        " <small class='text-muted'>/ " .
        number_format($weightPerCard, 1, ",", ".") .
        "</small></td>";

    echo "<td class='text-right cell-bias-main'>" .
        number_format($catPercentageWithBias, 2, ",", ".") .
        " %</td>";

    echo "<td class='text-right cell-bias-sub'>" .
        number_format($singleIdPercentageWithBias, 4, ",", ".") .
        " %</td>";

    // === OHNE GEWICHTUNG (2 Spalten) ===
    echo "<td class='text-right cell-nobias-main'>" .
        number_format($catPercentageNoBias, 2, ",", ".") .
        " %</td>";

    echo "<td class='text-right cell-nobias-sub'>" .
        number_format($singleIdPercentageNoBias, 4, ",", ".") .
        " %</td>";

    echo "</tr>";
}
echo "</table>";
echo "<br><p><em class='text-muted'>Hinweis zur Spalte 'Ohne Gewichtung': Hier hat jede gezोने Zahl von 1 bis 60 die exakt gleiche Chance (1 zu 60 oder ca. 1,66%). Die Kategorie-Chance ergibt sich hier rein aus der Anzahl an IDs, die dieser Kategorie zugeordnet sind.</em></p>";
?>

</body>
</html>
