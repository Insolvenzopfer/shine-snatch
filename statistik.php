<?php
/**
 * Shine-Snatch Statistik Generator (SQL-Version)
 */
$config = require "config.php";

$maskLength = 5; // Bestimmt, wie viele Zeichen vom Spielernamen sichtbar bleiben (z.B. Insol*****)
$limitDays = 10; // Nur Einträge der letzten X Tage berücksichtigen
$targetContext = $_GET["context"] ?? "";

date_default_timezone_set("Europe/Berlin");

// Zentrale DB-Verbindung laden
require_once "db.php";
$pdo = getDatabaseConnection();

// Hilfsfunktion zur Zensur des Spieler-Namens (mit Sonderregel für kurze Namen)
function maskName($name, $visibleChars)
{
    $len = mb_strlen($name);

    // SONDERREGEL: Wenn der Name weniger als 6 Zeichen hat
    if ($len < 6) {
        // Mindestens 2 Zeichen am Ende maskieren, der Rest bleibt sichtbar
        $visibleCount = max(1, $len - 2);
        return mb_substr($name, 0, $visibleCount) .
            str_repeat("*", $len - $visibleCount);
    }

    // STANDARDREGEL: Für Namen ab 6 Zeichen
    return $len <= $visibleChars
        ? $name
        : mb_substr($name, 0, $visibleChars) .
                str_repeat("*", $len - $visibleChars);
}

// 1. SCHRITT: Alle verfügbaren Kontexte (Server-Namen) ermitteln
$cutoffDate = date(
    "Y-m-d H:i:s",
    strtotime("today - " . ($limitDays - 1) . " days"),
);

$stmtContexts = $pdo->prepare(
    "SELECT DISTINCT server_name FROM snatch_logs WHERE created_at >= ? AND server_name != '' ORDER BY server_name ASC",
);
$stmtContexts->execute([$cutoffDate]);
$availableContexts = $stmtContexts->fetchAll(PDO::FETCH_COLUMN);

if (empty($targetContext) && !empty($availableContexts)) {
    $targetContext = $availableContexts[0];
}

// 2. SCHRITT: Daten für den ausgewählten Kontext abfragen
$entries = [];
$userStats = [];
$cardStats = []; // Array für die Auswertung der gezogenen Karten
$dailyWinners = [];

if (!empty($targetContext)) {
    // JOIN mit snatch_users, um den echten display_name zu holen
    $stmtLogs = $pdo->prepare("
        SELECT l.id, l.created_at, l.server_name, l.total_points, l.pulled_cards, l.user_id, u.display_name
        FROM snatch_logs l
        JOIN snatch_users u ON l.user_id = u.id
        WHERE l.server_name = ? AND l.created_at >= ?
        ORDER BY l.created_at DESC
    ");
    $stmtLogs->execute([$targetContext, $cutoffDate]);
    $dbLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Tages-Bestwerte (Gewinner) via SQL ermitteln
    $stmtWinners = $pdo->prepare("
        SELECT DATE(created_at) as log_date, MAX(total_points) as max_pts
        FROM snatch_logs
        WHERE server_name = ? AND created_at >= ?
        GROUP BY DATE(created_at)
    ");
    $stmtWinners->execute([$targetContext, $cutoffDate]);
    $dailyWinners = $stmtWinners->fetchAll(PDO::FETCH_KEY_PAIR);

    // Daten für die Ausgabe aufbereiten
    foreach ($dbLogs as $row) {
        $timestamp = strtotime($row["created_at"]);
        $dateKey = date("Y-m-d", $timestamp);

        $realName = !empty($row["display_name"])
            ? $row["display_name"]
            : "User #" . $row["user_id"];
        $maskedPlayerName = maskName($realName, $maskLength);

        $entries[] = [
            "date" => date("d.m.Y", $timestamp),
            "time" => date("H:i", $timestamp),
            "rawDate" => $dateKey,
            "user" => $maskedPlayerName,
            "points" => (int) $row["total_points"],
            "cards" => $row["pulled_cards"],
        ];

        // --- KARTEN-ZEHLUNG (NEU) ---
        if (!empty($row["pulled_cards"])) {
            // Falls mehrere Karten durch Komma getrennt sind, splitten wir sie auf
            $cardsArray = explode(",", $row["pulled_cards"]);
            foreach ($cardsArray as $cardName) {
                $cardName = trim($cardName); // Leerzeichen entfernen
                if ($cardName !== "") {
                    if (!isset($cardStats[$cardName])) {
                        $cardStats[$cardName] = 0;
                    }
                    $cardStats[$cardName]++;
                }
            }
        }

        // Spieler-Statistiken aggregieren
        if (!isset($userStats[$maskedPlayerName])) {
            $userStats[$maskedPlayerName] = [
                "displayName" => $maskedPlayerName,
                "count" => 0,
                "highest" => -999,
                "lowest" => 999,
                "sum" => 0,
            ];
        }

        $userStats[$maskedPlayerName]["count"]++;
        $userStats[$maskedPlayerName]["sum"] += (int) $row["total_points"];
        if (
            (int) $row["total_points"] >
            $userStats[$maskedPlayerName]["highest"]
        ) {
            $userStats[$maskedPlayerName]["highest"] =
                (int) $row["total_points"];
        }
        if (
            (int) $row["total_points"] < $userStats[$maskedPlayerName]["lowest"]
        ) {
            $userStats[$maskedPlayerName]["lowest"] =
                (int) $row["total_points"];
        }
    }

    // Sortierung der Spieler-Leistung nach dem Durchschnitt (Höchster zuerst)
    uasort($userStats, function ($a, $b) {
        $avgA = $a["sum"] / $a["count"];
        $avgB = $b["sum"] / $b["count"];
        return $avgB <=> $avgA;
    });

    // Sortierung der Karten nach Häufigkeit (Oft gezogene zuerst) (NEU)
    arsort($cardStats);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Shine-Snatch Highscores & Statistiken</title>
    <style>
        :root {
            --bg: #050705;
            --panel: rgba(20, 25, 20, 0.7);
            --primary: #2cb24c;
            --accent: #a855f7;
            --card-charts: #eab308; /* Neue Farbe für die Karten-Charts (Gold/Gelb) */
            --text: #e2e8f0;
            --card-bg: rgba(255,255,255,0.03);
        }
        body {
            font-family: 'Signika', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 30px 15px;
        }
        .container { max-width: 1200px; margin: 0 auto; } /* Leicht verbreitert für das 3er-Layout */
        h1 { text-align: center; color: var(--primary); font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 2px; }
        .subtitle { text-align: center; opacity: 0.5; margin-bottom: 30px; font-weight: 300; }
        .tabs { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 30px; }
        .tab-btn {
            background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1); color: var(--text);
            padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 0.95rem; transition: all 0.2s;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.1); border-color: var(--primary); }
        .tab-btn.active { background: rgba(44, 178, 76, 0.2); border-color: var(--primary); color: #fff; font-weight: 600; box-shadow: 0 0 15px rgba(44,178,76,0.2); }
        .card-box { background: var(--panel); backdrop-filter: blur(10px); border-radius: 20px; padding: 25px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; font-size: 1.3rem; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px; margin-bottom: 15px; }
        .title-chronik { color: var(--accent); }
        .title-spieler { color: var(--primary); }
        .title-karten { color: var(--card-charts); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px 10px; color: var(--text); opacity: 0.4; font-weight: 300; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.95rem; vertical-align: middle; }
        .date-row td { background: rgba(168, 85, 247, 0.05); color: var(--accent); font-weight: 600; padding: 8px 10px; font-size: 0.85rem; border-bottom: 1px solid rgba(168, 85, 247, 0.2); }
        code { background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 6px; color: #f472b6; font-family: monospace; font-size: 0.9rem; }
        .highlight { color: var(--primary); font-weight: 600; }
        .winner-label { background: linear-gradient(45deg, #eab308, #ca8a04); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; margin-left: 8px; display: inline-flex; align-items: center; }
        .no-data { text-align: center; opacity: 0.5; padding: 40px 0; }

        /* Grid-Layout für 3 Spalten nebeneinander auf großen Bildschirmen */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🎰 Snatch Lore & Analytics</h1>
    <div class="subtitle">Live-Auswertung der letzten <?php echo $limitDays; ?> Tage</div>

    <div class="tabs">
        <?php foreach ($availableContexts as $ctx): ?>
            <a href="?context=<?php echo urlencode(
                $ctx,
            ); ?>" class="tab-btn <?php echo $ctx === $targetContext
    ? "active"
    : ""; ?>">
                🌐 <?php echo htmlspecialchars($ctx); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($targetContext) || empty($entries)): ?>
        <div class="card-box no-data">
            <h3>Keine Daten in diesem Zeitraum vorhanden</h3>
            <p>Es wurden in den letzten <?php echo $limitDays; ?> Tagen keine Ziehungen für diesen Kontext aufgezeichnet.</p>
        </div>
    <?php else: ?>

    <div class="stats-grid">

        <!-- SPALTE 1: CHRONIK -->
        <div class="card-box">
            <h2 class="title-chronik">📜 Chronik der Ziehungen</h2>
            <table>
                <thead>
                    <tr><th>Uhrzeit / Spieler</th><th>Ergebnis</th></tr>
                </thead>
                <tbody>
                    <?php
                    $lastDate = "";
                    foreach ($entries as $entry):

                        $currentDate = $entry["date"];
                        if ($currentDate !== $lastDate): ?>
                        <tr class="date-row">
                            <td colspan="2">📅 <?php echo $currentDate; ?></td>
                        </tr>
                    <?php endif;
                        $isWinner =
                            isset($dailyWinners[$entry["rawDate"]]) &&
                            $entry["points"] ===
                                (int) $dailyWinners[$entry["rawDate"]];
                        ?>
                    <tr>
                        <td>
                            <span style="opacity:0.4; font-size:0.8rem; margin-right:5px;"><?php echo $entry[
                                "time"
                            ]; ?></span>
                            <code><?php echo htmlspecialchars(
                                $entry["user"],
                            ); ?></code>
                            <?php if ($isWinner): ?>
                                <span class="winner-label">👑 Tages-Bestwert</span>
                            <?php endif; ?>
                            <br><small style="opacity: 0.4; font-size: 0.75rem;">Hand: <?php echo htmlspecialchars(
                                $entry["cards"],
                            ); ?></small>
                        </td>
                        <td class="highlight" style="text-align: right; white-space: nowrap;"><?php echo $entry[
                            "points"
                        ]; ?> Pkt</td>
                    </tr>
                    <?php $lastDate = $currentDate;
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>

        <!-- SPALTE 2: SPIELER LEISTUNG -->
        <div class="card-box" style="align-self: start;">
            <h2 class="title-spieler">👤 Spieler-Leistung (Ø Schnitt)</h2>
            <table>
                <thead>
                    <tr><th>Spieler</th><th>Züge</th><th>Max</th><th>Min</th><th style="text-align: right;">Ø Schnitt</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($userStats as $u): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars(
                            $u["displayName"],
                        ); ?></code></td>
                        <td><?php echo $u["count"]; ?></td>
                        <td style="color: #4ade80;"><?php echo $u[
                            "highest"
                        ]; ?></td>
                        <td style="color: #f87171;"><?php echo $u[
                            "lowest"
                        ]; ?></td>
                        <td class="highlight" style="text-align: right;"><?php echo round(
                            $u["sum"] / $u["count"],
                            1,
                        ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SPALTE 3: KARTEN CHARTS (NEU) -->
        <div class="card-box" style="align-self: start;">
            <h2 class="title-karten">🃏 Top gezogene Karten</h2>
            <table>
                <thead>
                    <tr><th>Platz</th><th>Karte</th><th style="text-align: right;">Häufigkeit</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($cardStats)): ?>
                        <tr><td colspan="3" style="text-align:center; opacity:0.5; padding: 20px;">Es wurden noch keine Karten aufgezeichnet.</td></tr>
                    <?php else:$rank = 1;
                        foreach ($cardStats as $cardName => $count):

                            $rankDisplay = $rank;
                            if ($rank === 1) {
                                $rankDisplay = "🥇";
                            }
                            if ($rank === 2) {
                                $rankDisplay = "🥈";
                            }
                            if ($rank === 3) {
                                $rankDisplay = "🥉";
                            }
                            ?>
                        <tr>
                            <td style="width: 40px; font-weight: bold;"><?php echo $rankDisplay; ?></td>
                            <td><span style="font-size: 1.05rem;"><?php echo htmlspecialchars(
                                $cardName,
                            ); ?></span></td>
                            <td style="text-align: right; font-weight: 600; color: var(--card-charts);"><?php echo $count; ?>x</td>
                        </tr>
                        <?php $rank++;
                        endforeach;endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <?php endif; ?>
</div>

</body>
</html>
