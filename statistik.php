<?php
/**
 * Shine-Snatch Statistik Generator
 */

// --- EINSTELLUNGEN ---
$logFile     = 'snatchlog.txt';
$maskLength  = 5; 
$limitDays   = 4; // <--- NEU: Nur die letzten X Tage berücksichtigen
$targetContext = $_GET['context'] ?? ''; 

date_default_timezone_set('Europe/Berlin');

if (!file_exists($logFile)) {
    die("Log-Datei nicht gefunden.");
}

// Hilfsfunktion zur Zensur
function maskName($name, $visibleChars) {
    $len = mb_strlen($name);
    return ($len <= $visibleChars) ? $name : mb_substr($name, 0, $visibleChars) . str_repeat('*', $len - $visibleChars);
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// 1. SCHRITT: Contexts finden & Zeitfilter vorbereiten
$availableContexts = [];
$cutoffDate = date('Y-m-d', strtotime("today - " . ($limitDays - 1) . " days"));

foreach ($lines as $line) {
    $parts = explode('|', $line);
    if (count($parts) >= 3) {
        $ctx = trim($parts[2]);
        if (!empty($ctx)) $availableContexts[$ctx] = true;
    }
}
$availableContexts = array_keys($availableContexts);
sort($availableContexts);

if (empty($targetContext)) $targetContext = $availableContexts[0] ?? '';

// 2. SCHRITT: Daten verarbeiten
$stats = [
    'userStats' => [], 
    'cardCount' => [], 
    'dailyFirsts' => [], 
    'dayHighscores' => []
];

foreach ($lines as $line) {
    $parts = explode('|', $line);
    if (count($parts) < 7) continue;

    // Context Filter
    $context = trim($parts[2]);
    if ($context !== $targetContext) continue;

    $dateFull = trim($parts[0]);
    $day      = substr($dateFull, 0, 10);
    
    // NEU: Datums-Filter (nur Einträge der letzten X Tage)
    if ($day < $cutoffDate) continue;

    $rawUser  = trim(str_replace('(Spieler)', '', $parts[4]));
    $points   = (int)filter_var($parts[5], FILTER_SANITIZE_NUMBER_INT);
    
    preg_match('/Hand:\s*([\d,]+)/', $parts[6], $matches);
    $hand = isset($matches[1]) ? explode(',', $matches[1]) : [];

    // Tages-Höchstwert tracken
    if (!isset($stats['dayHighscores'][$day]) || $points > $stats['dayHighscores'][$day]) {
        $stats['dayHighscores'][$day] = $points;
    }

    // Karten zählen
    foreach ($hand as $cardId) {
        $cardId = trim($cardId);
        if ($cardId !== "") $stats['cardCount'][$cardId] = ($stats['cardCount'][$cardId] ?? 0) + 1;
    }

// 1. User Stats wie gehabt
if (!isset($stats['userStats'][$rawUser])) {
    $stats['userStats'][$rawUser] = ['displayName' => maskName($rawUser, $maskLength), 'count' => 0, 'highest' => 0, 'lowest' => 9999, 'sum' => 0];
}
$stats['userStats'][$rawUser]['count']++;
$stats['userStats'][$rawUser]['sum'] += $points;
if ($points > $stats['userStats'][$rawUser]['highest']) $stats['userStats'][$rawUser]['highest'] = $points;
if ($points < $stats['userStats'][$rawUser]['lowest']) $stats['userStats'][$rawUser]['lowest'] = $points;

// 2. Daily First Logik & separater Highscore NUR für diese Liste
$dayUserKey = $day . '_' . $rawUser;
if (!isset($stats['dailyFirsts'][$dayUserKey])) {
    $stats['dailyFirsts'][$dayUserKey] = [
        'time' => $dateFull, 
        'user' => maskName($rawUser, $maskLength), 
        'points' => $points,
        'dayKey' => $day
    ];
    
    // NEU: Wir prüfen den Highscore NUR innerhalb der ersten täglichen Ziehungen
    if (!isset($stats['firstsHighscore'][$day]) || $points > $stats['firstsHighscore'][$day]) {
        $stats['firstsHighscore'][$day] = $points;
    }
}
}

arsort($stats['cardCount']);
$topCards = array_slice($stats['cardCount'], 0, 10, true);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Snatch Statistik</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #eee; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .header-area { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e1e1e; padding-bottom: 20px; }
        h1 { color: #7e22ce; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px; }
        .context-select { background: #1e1e1e; color: #facc15; border: 1px solid #7e22ce; padding: 10px; border-radius: 5px; cursor: pointer; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card-box { background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; }
        h2 { color: #00ff00; font-size: 1.1em; border-bottom: 1px solid #333; padding-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: #aaa; font-size: 0.75rem; padding: 10px; border-bottom: 2px solid #333; }
        td { padding: 10px; border-bottom: 1px solid #2a2a2a; }
        
        .highlight { color: #facc15; font-weight: bold; }
        code { background: #000; padding: 2px 6px; border-radius: 4px; color: #38bdf8; font-family: monospace; }
        
        .date-separator td { border-top: 3px solid #7e22ce !important; padding-top: 15px !important; }
        .date-header { color: #facc15; font-size: 0.8rem; font-weight: bold; }
        
        .day-winner { background: rgba(250, 204, 21, 0.07) !important; }
        .winner-label { font-size: 0.65rem; color: #facc15; text-transform: uppercase; display: block; font-weight: bold; }
        
        .bar-container { background: #000; border-radius: 10px; height: 10px; width: 80px; display: inline-block; border: 1px solid #333; }
        .bar-fill { background: linear-gradient(90deg, #7e22ce, #a855f7); height: 100%; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-area">
        <h1>📊 Snatch-Statistik</h1>
        <form method="GET" id="contextForm">
            <select name="context" class="context-select" onchange="this.form.submit()">
                <?php foreach ($availableContexts as $ctx): ?>
                    <option value="<?php echo htmlspecialchars($ctx); ?>" <?php echo ($ctx === $targetContext) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ctx); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <p><small>Anzeige der letzten <strong><?php echo $limitDays; ?> Tage</strong></small></p>
    </div>

    <div class="grid">
        <div class="card-box">
            <h2>🏆 Top Karten (Last <?php echo $limitDays; ?>d)</h2>
            <table>
                <?php foreach ($topCards as $id => $count): ?>
                <tr>
                    <td>Karte #<?php echo $id; ?></td>
                    <td>
                        <div class="bar-container"><div class="bar-fill" style="width: <?php echo min(100, $count * 10); ?>%"></div></div>
                        <?php echo $count; ?>x
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card-box">
            <h2>📅 Erste Tages-Ziehungen</h2>
            <table>
                <tbody>
                    <?php 
$recentFirsts = array_reverse($stats['dailyFirsts']);
$lastDate = null;

foreach ($recentFirsts as $entry): 
    $currentDate = date('d.m.Y', strtotime($entry['time']));
    $isNewDay = ($lastDate !== null && $lastDate !== $currentDate);
    
    // Jetzt prüfen wir gegen den Highscore der ERSTEN ZIEHUNGEN dieses Tages
    $isWinner = ($entry['points'] === ($stats['firstsHighscore'][$entry['dayKey']] ?? null));
?>
    <tr class="<?php echo $isNewDay ? 'date-separator' : ''; ?> <?php echo $isWinner ? 'day-winner' : ''; ?>">
        <td>
            <?php if ($isNewDay || $lastDate === null): ?>
                <span class="date-header"><?php echo $currentDate; ?></span><br>
            <?php endif; ?>
            <small><?php echo date('H:i', strtotime($entry['time'])); ?> Uhr</small>
        </td>
        <td>
            <?php if ($isWinner): ?>
                <span class="winner-label">👑 Tages-Bestwert</span>
            <?php endif; ?>
            <code><?php echo $entry['user']; ?></code>
        </td>
        <td class="highlight"><?php echo $entry['points']; ?> Pkt</td>
    </tr>
<?php 
    $lastDate = $currentDate; 
endforeach; 
?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-box" style="margin-top: 20px;">
        <h2>👤 Spieler-Leistung (Letzte <?php echo $limitDays; ?> Tage)</h2>
        <table>
            <thead>
                <tr>
                    <th>Spieler</th><th>Ziehungen</th><th>Beste</th><th>Niedrigste</th><th>Ø Schnitt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['userStats'] as $u): ?>
                <tr>
                    <td><code><?php echo $u['displayName']; ?></code></td>
                    <td><?php echo $u['count']; ?></td>
                    <td style="color: #4ade80;"><?php echo $u['highest']; ?></td>
                    <td style="color: #f87171;"><?php echo $u['lowest']; ?></td>
                    <td class="highlight"><?php echo round($u['sum'] / $u['count'], 1); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>