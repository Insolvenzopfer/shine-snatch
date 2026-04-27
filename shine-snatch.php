<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(["error" => "No input"]));

/**
 * Kernfunktion für die Theme-Verwaltung
 */
function processThemeLogic($themeInput, $actorName, $themes) {
    $presetsFile = 'user_presets.json';
    $status = "Standard";
    
    // Mapping für Case-Insensitive Suche
    $themeMapLower = [];
    foreach ($themes as $key => $val) {
        $themeMapLower[mb_strtolower((string)$key)] = $key;
    }

    $userPresets = [];
    if (file_exists($presetsFile) && filesize($presetsFile) > 0) {
        $userPresets = json_decode(file_get_contents($presetsFile), true) ?? [];
    }

    $workTheme = mb_strtolower(trim((string)$themeInput));

    // --- A) SETZEN LOGIK ---
    if (str_starts_with($workTheme, 'set:')) {
        $fullStoreValue = trim(substr($themeInput, 4));
        $userPresets[$actorName] = $fullStoreValue;
        @file_put_contents($presetsFile, json_encode($userPresets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $themeInput = $fullStoreValue;
        $status = "Preset gespeichert";
    } 
    // --- B) AUTOMATISCHES LADEN ---
    elseif (($workTheme === 'gold' || empty($workTheme)) && !empty($actorName)) {
        if (isset($userPresets[$actorName])) {
            $themeInput = $userPresets[$actorName];
            $status = "Preset geladen";
        }
    }

    // --- C) THEME-KOMPONENTEN TRENNEN ---
    $parts = explode(',', (string)$themeInput);
    $primary = mb_strtolower(trim($parts[0] ?? 'gold'));
    $secondary = mb_strtolower(trim($parts[1] ?? 'gold'));

    $allKeys = array_keys($themes);
    $finalKey = "Gold"; // Absoluter Standard

    // 1. Priorität: Zufall direkt
    if ($primary === 'zufall') {
        $finalKey = $allKeys[array_rand($allKeys)];
    } 
    // 2. Priorität: Kombo-Theme Modus
    elseif ($primary === 'kombo-theme') {
        if ($secondary === 'zufall') {
            $finalKey = $allKeys[array_rand($allKeys)];
        } else {
            // Prüfen ob das Fallback-Theme nach dem Komma existiert
            $finalKey = $themeMapLower[$secondary] ?? "Gold";
        }
    } 
    // 3. Priorität: Existiert das gesetzte Theme im JSON?
    elseif (isset($themeMapLower[$primary])) {
        $finalKey = $themeMapLower[$primary];
    }
    // 4. Fallback: Immer Gold (bereits durch Initialisierung gesetzt)

    return [
        "cfg" => $themes[$finalKey] ?? $themes[array_keys($themes)[0]], // Falls Gold auch fehlt, nimm das erste
        "key" => $finalKey,
        "isKomboMode" => ($primary === 'kombo-theme'),
        "status" => $status
    ];
}

// 1. DATEN & THEME laden

$themes = json_decode(file_get_contents('themes.json'), true);

// NEU: Prüfung auf Preview-Mode VOR der normalen Logik
if (($input['theme'] ?? '') === "PREVIEW_MODE" && isset($input['customConfig'])) {
    // Wenn Vorschau, nutzen wir direkt die mitschickten Daten aus dem Editor
    $cfg = $input['customConfig'];
    $finalKey = "Vorschau";
    $isKomboMode = false; // In der Vorschau meist nicht gewünscht
} else {
    // Normaler Ablauf für das Spiel
    $res = processThemeLogic($input['theme'] ?? 'Gold', $input['actorName'] ?? null, $themes);
    $cfg = $res['cfg'];
    $finalKey = $res['key'];
    $isKomboMode = $res['isKomboMode'];
}

// --- 2. DECK & KARTEN ---
$cardDist = [
    ["name" => "Edelstein/Gegenstand", "count" => 7, "points" => 5, "startId" => 1, "emoji" => "💎"],
    ["name" => "Bestie/Monster", "count" => 7, "points" => 5, "startId" => 8, "emoji" => "👾"],
    ["name" => "Ort", "count" => 6, "points" => 5, "startId" => 15, "emoji" => "🏰"],
    ["name" => "Künstler/Händler", "count" => 6, "points" => 5, "startId" => 21, "emoji" => "👨‍🌾"],
    ["name" => "Mystisch", "count" => 5, "points" => 10, "startId" => 27, "emoji" => "🔮"],
    ["name" => "Scout", "count" => 5, "points" => 10, "startId" => 32, "emoji" => "🏹"],
    ["name" => "Krieger", "count" => 5, "points" => 10, "startId" => 37, "emoji" => "⚔️"],
    ["name" => "Magier", "count" => 5, "points" => 10, "startId" => 42, "emoji" => "🧙"],
    ["name" => "Magische Kreatur", "count" => 4, "points" => 15, "startId" => 47, "emoji" => "🦄"],
    ["name" => "Prinz/Prinzessin", "count" => 4, "points" => 15, "startId" => 51, "emoji" => "👑"],
    ["name" => "König/Königin", "count" => 4, "points" => 20, "startId" => 55, "emoji" => "🤴"],
    ["name" => "Höheres Wesen", "count" => 2, "points" => 30, "startId" => 59, "emoji" => "✨"]
];

// 2. Deck bauen (Muss auch immer laufen, damit wir Karten-Daten für die IDs haben!)
$deck = [];
foreach ($cardDist as $c) {
    for ($i = 0; $i < $c['count']; $i++) {
        $deck[] = ["name" => $c['name'], "points" => $c['points'], "id" => $c['startId'] + $i, "emoji" => $c['emoji']];
    }
}

if (isset($input['overrideHand']) && is_array($input['overrideHand'])) {
    $hand = [];
    foreach ($input['overrideHand'] as $searchId) {
        foreach ($deck as $card) {
            if ($card['id'] == $searchId) { $hand[] = $card; break; }
        }
    }
} else {
    shuffle($deck);
    $hand = array_slice($deck, 0, 5);
}

// 3. WÜRFELN
$specBonusTotal = 0;
$specHits = [];
foreach ($hand as $card) {
    if (in_array($card['id'], $input['ownedCards'] ?? [])) {
        $roll = rand(1, 4);
        $specBonusTotal += $roll;
        $specHits[] = "#{$card['id']} ($roll)";
    }
}

// 4. SYNERGIEN
$combos = [];
function addC($label, $points, $types, $hand, &$combos) {
    $matchedIndices = [];
    foreach ($types as $t) {
        $found = false;
        foreach ($hand as $idx => $card) {
            if ($card['name'] === $t && !in_array($idx, $matchedIndices)) {
                $matchedIndices[] = $idx;
                $found = true;
                break;
            }
        }
        if (!$found) return;
    }
    $combos[] = ["label" => $label, "points" => $points, "indices" => $matchedIndices, "ids" => array_map(fn($i) => $hand[$i]['id'], $matchedIndices)];
}

$counts = array_count_values(array_column($hand, 'name'));


// Alle Regeln aus deinem Macro
if (($counts['Bestie/Monster'] ?? 0) >= 5) addC("🐾🐾🐾 Bestienhorde (5)", 60, array_fill(0, 5, "Bestie/Monster"), $hand, $combos);
if (($counts['Bestie/Monster'] ?? 0) >= 4) addC("🐾🐾 Bestienhorde (4)", 30, array_fill(0, 4, "Bestie/Monster"), $hand, $combos);
if (($counts['Bestie/Monster'] ?? 0) >= 3) addC("🐾 Bestienhorde (3)", 15, array_fill(0, 3, "Bestie/Monster"), $hand, $combos);

if (isset($counts['Edelstein/Gegenstand'])) {
    if (($counts['Ort'] ?? 0) >= 4) addC("🗺️🗺️🗺️ Karte (4+)", 60, ["Edelstein/Gegenstand", "Ort", "Ort", "Ort", "Ort"], $hand, $combos);
    if (($counts['Ort'] ?? 0) >= 3) addC("🗺️🗺️ Karte (3)", 30, ["Edelstein/Gegenstand", "Ort", "Ort", "Ort"], $hand, $combos);
    if (($counts['Ort'] ?? 0) >= 2) addC("🗺️ Karte (2)", 15, ["Edelstein/Gegenstand", "Ort", "Ort"], $hand, $combos);
}

addC("🏰 Großes Königreich", 40, ["König/Königin", "Prinz/Prinzessin", "Krieger", "Ort"], $hand, $combos);
addC("🏘️ Kleines Königreich (K)", 30, ["König/Königin", "Krieger", "Ort"], $hand, $combos);
addC("🏘️ Kleines Königreich (P)", 30, ["Prinz/Prinzessin", "Krieger", "Ort"], $hand, $combos);
addC("🛡️ Abenteuergruppe", 50, ["Magier", "Krieger", "Scout", "Mystisch"], $hand, $combos);
addC("⚖️ Markt", 20, ["Künstler/Händler", "Ort", "Edelstein/Gegenstand"], $hand, $combos);
addC("🔭 Magierturm", 15, ["Magier", "Ort"], $hand, $combos);
addC("🕊️⛩️💩 Holy Shit", 50, ["Höheres Wesen", "Mystisch", "Mystisch"], $hand, $combos);
addC("⚔️ Zu den Waffen", 30, ["König/Königin", "Krieger", "Krieger"], $hand, $combos);
addC("🐉 Drachen-Hort", 30, ["Magische Kreatur", "Edelstein/Gegenstand", "Edelstein/Gegenstand"], $hand, $combos);
addC("🦄 Entführte Prinzessin", 50, ["Magische Kreatur", "Prinz/Prinzessin", "Ort"], $hand, $combos);

if (($counts['Künstler/Händler'] ?? 0) >= 2 && isset($counts['Edelstein/Gegenstand'])) {
    addC("🐪 Große Karawane", 50, ["Künstler/Händler", "Künstler/Händler", "Edelstein/Gegenstand", "Krieger"], $hand, $combos);
    addC("📦 Kleine Karawane", 20, ["Künstler/Händler", "Künstler/Händler", "Edelstein/Gegenstand"], $hand, $combos);
}
addC("🏕️ Waldläufer Patrouille", 15, ["Scout", "Ort"], $hand, $combos);
addC("🏮 Schrein", 15, ["Mystisch", "Ort"], $hand, $combos);
addC("🔨 Schmiede", 35, ["Krieger", "Edelstein/Gegenstand", "Künstler/Händler"], $hand, $combos);
addC("🌌 Domänenentfaltung", 40, ["Höheres Wesen", "Höheres Wesen"], $hand, $combos);
addC("🐺 Rudelsführer", 35, ["Magische Kreatur", "Bestie/Monster", "Bestie/Monster"], $hand, $combos);
addC("🛡️ Paladine", 25, ["Mystisch", "Krieger", "Krieger"], $hand, $combos);
addC("🍃 Druide", 25, ["Mystisch", "Scout", "Bestie/Monster"], $hand, $combos);
addC("🧪 Für die Wissenschaft", 35, ["Magier", "Magische Kreatur", "Edelstein/Gegenstand"], $hand, $combos);
addC("👪 Königsfamilie", 45, ["König/Königin", "König/Königin", "Prinz/Prinzessin", "Prinz/Prinzessin"], $hand, $combos);
addC("🔱 Heilige Dreifaltigkeit", 35, ["Höheres Wesen", "Magische Kreatur", "Mystisch"], $hand, $combos);


// 5. OPTIMIERUNG
function findBest($combos, $used = []) {
    $best = ["combos" => [], "pts" => 0];
    foreach ($combos as $i => $c) {
        if (!array_intersect($c['indices'], $used)) {
            $res = findBest(array_slice($combos, $i + 1), array_merge($used, $c['indices']));
            if ($c['points'] + $res['pts'] > $best['pts']) {
                $best['pts'] = $c['points'] + $res['pts'];
                $best['combos'] = array_merge([$c], $res['combos']);
            }
        }
    }
    return $best;
}

$opt = findBest($combos);
$usedIdx = array_merge(...array_column($opt['combos'], 'indices') ?: [[]]);

// --- 5.1. KOMBO-OVERWRITE ---
if ($isKomboMode && !empty($opt['combos'])) {
    $bestLabel = $opt['combos'][0]['label'];
    $mapping = [
        "Bestienhorde" => "Rudel", 
        "Königreich" => "Royal", 
        "Karte" => "Entdecker",
        "Abenteuer" => "Helden", 
        "Markt" => "Handel", 
        "Magierturm" => "Arkan",
        "Holy Shit" => "Goettlich", 
        "Waffen" => "Krieg", 
        "HerDrachen-Hortde" => "Drache",
        "Prinzessin" => "Royal", 
        "Karawane" => "Handel", 
        "Patrouille" => "Wald",
        "Schrein" => "Mystik", 
        "Schmiede" => "Schmiede", 
        "Sphären" => "Kosmisch",
        "Rudelsführer" => "Rudel", 
        "Paladine" => "Heilig", 
        "Druide" => "Natur",
        "Wissenschaft" => "Alchemie", 
        "Königsfamilie"=> "Empire", 
        "Dreifaltigkeit"=> "Heilig"
    ];

    foreach ($mapping as $key => $themeName) {
        if (mb_strpos($bestLabel, $key) !== false && isset($themes[$themeName])) {
            $cfg = $themes[$themeName];
            break; 
        }
    }
}


// --- 6. HTML GENERIERUNG (Vollständige Version) ---
$base = array_sum(array_column($hand, 'points'));
$subTotal = $base + $specBonusTotal;

// Hand-Liste
$listHtml = "";
foreach ($hand as $i => $c) {
    $isUsed = in_array($i, $usedIdx);
    $isSpecial = in_array($c['id'], $input['ownedCards'] ?? []);
    
    $st = $isUsed 
        ? "color:{$cfg['colorTextMuted']}; text-decoration:line-through;" 
        : "font-weight:bold; color:{$cfg['colorBoltCore']}; text-shadow:0 0 0px " . ($cfg['colorGlowMain'] ?? 'transparent') . ";";
    
    $si = $isSpecial ? "<span style='color:{$cfg['colorPrimary']}; animation:blink 1s infinite'  data-edit-keys='colorPrimary'>{$cfg['specialCardEmoji']}</span>" : "";
    
    $listHtml .= "
    <li style='border-bottom: 1px solid #333; padding: 2px 0; list-style: none;'>
        $si <span style='$st'  data-edit-keys='colorTextMuted,colorBoltCore,colorGlowMain'>{$c['emoji']} {$c['name']}</span> 
        <small style='color:{$cfg['colorAccent']}; opacity:0.8;' data-edit-keys='colorAccent'>#{$c['id']}</small>
        <span style='float:right; color:{$cfg['colorTextMain']}'  data-edit-keys='colorTextMain'>{$c['points']} Pkt</span>
    </li>";
}

// Aktive Synergien
$activeHtml = "";
foreach($opt['combos'] as $c) {
    $activeHtml .= "
    <div style='color:{$cfg['colorBoltCore']}; margin-bottom: 2px; text-shadow: 0 0 0px " . ($cfg['colorGlowMain'] ?? 'transparent') . ";'  data-edit-keys='colorGlowMain,colorBoltCore' > 
        {$cfg['iconCombo']} {$c['label']} 
        <small style='color:{$cfg['colorTextComboIds']}; font-size: 0.8em;'  data-edit-keys='colorTextComboIds'>(#".implode(", ", $c['ids']).")</small> 
        <span style='float:right; color:{$cfg['colorPrimary']};' data-edit-keys='colorPrimary'>+{$c['points']}</span>
    </div>";
}

// Verfallene Pfade (Nicht genutzte Kombis)
$unusedHtml = "";
$unusedCombos = array_filter($combos, function($c) use ($opt) {
    return !in_array($c, $opt['combos']);
});

foreach ($unusedCombos as $c) {
    // Wir fügen hier die IDs der Karten hinzu, die diese Kombo gebildet hätten
    $unusedHtml .= "
    <div style='color: {$cfg['colorTextMuted']}; font-size: 0.9em; margin-bottom: 1px;' data-edit-keys='colorTextMuted'>
        {$cfg['iconUnused']} {$c['label']} 
        <small style='opacity: 0.8; font-size: 1em;'>(#".implode(", ", $c['ids']).")</small>
        <span style='float:right; opacity: 0.8;'>+{$c['points']}</span>
    </div>";
}

// --- 7. FINALES TEMPLATE ---
$total = $subTotal + $opt['pts'];

$html = "
<div style='font-family: \"Signika\", sans-serif; border: 2px solid {$cfg['colorAccent']}; border-radius: 10px; background: {$cfg['colorBg']}; padding: 12px; color: {$cfg['colorTextMain']}; box-shadow: 0 6px 12px {$cfg['shadowColor']};' data-edit-key='shadowColor,colorTextMain,colorBg,colorAccent'>
<h2 style='border-bottom: 2px solid {$cfg['colorPrimary']}; margin-top: 0; text-align: center; color: {$cfg['colorBoltCore']}; text-transform: uppercase; text-shadow: 0 0 10px {$cfg['colorPrimary']}, 0 0 20px {$cfg['colorPrimary']};'  data-edit-keys='colorPrimary,colorBoltCore,colorPrimary'>
        {$cfg['headerIcon']} <span style='font-weight: bold;'>{$cfg['headerTitle']}</span>
    </h2>
    
    <p style='margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorAccent']};'  data-edit-keys='colorAccent'>{$cfg['labelHand']}</p>
    <ul style='list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid #333; border-radius: 4px; background: {$cfg['colorBgCard']};' data-edit-keys='colorBgCard'>
        $listHtml
    </ul>
    
    <div style='text-align: right; font-size: 0.85em; color: {$cfg['colorTextMuted']}; margin-bottom: 5px;'  data-edit-keys='colorTextMuted'>
        {$cfg['labelHandSum']} <strong style='color: {$cfg['colorBoltCore']};' data-edit-keys='colorBoltCore'>$base Pkt</strong>
    </div>

    " . ($specBonusTotal > 0 ? "
    <div style='padding: 5px; background: " . ($cfg['colorSpecialBg'] ?? 'rgba(74, 222, 128, 0.1)') . "; border: 1px solid {$cfg['colorPrimary']}; border-radius: 4px; margin-bottom: 10px; font-size: 0.9em;'  data-edit-keys='colorPrimary,colorSpecialBg'>
        <span style='color: {$cfg['colorPrimary']}; font-weight: bold;' data-edit-keys='colorPrimary'>{$cfg['labelSpecialBonus']}</span>
        <span style='float: right; color: {$cfg['colorBoltCore']}; font-weight: bold;'  data-edit-keys='colorBoltCore'>+$specBonusTotal Pkt</span>
        <div style='font-size: 0.9em; color: {$cfg['colorTextMain']}; opacity: 0.8;'  data-edit-keys='colorTextMain' >Gewürfelt: " . implode(", ", $specHits) . "</div>
    </div>
    <div style='text-align: right; font-size: 0.9em; color: {$cfg['colorTextMain']}; border-top: 1px solid #333; margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;'  data-edit-keys='colorTextMain'>
        {$cfg['labelSubTotal']} <strong style='color: {$cfg['colorBoltCore']};' data-edit-keys='colorBoltCore'>$subTotal Pkt</strong>
    </div>" : "") . "

    <div>
        <p style='margin: 0 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorAccent']};' data-edit-keys='colorAccent'>{$cfg['labelCombos']}</p>
        <div style='padding: 8px; background: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid {$cfg['colorPrimary']};' data-edit-keys='colorPrimary'>
            " . ($activeHtml ?: "<i style='color: {$cfg['colorTextMuted']};' data-edit-keys='colorTextMuted'>Keine Synergien...</i>") . "
        </div>
    </div>

    " . ($unusedHtml ? "
    <div style='margin-top: 10px; opacity: 0.7;'>
        <p style='margin: 0 0 2px 0; font-size: 0.9em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorTextMuted']};' data-edit-keys='colorTextMuted'>{$cfg['labelUnused']}</p>
        <div style='padding: 4px 8px; border-left: 2px solid {$cfg['colorTextMuted']};' data-edit-keys='colorTextMuted'>
            $unusedHtml
        </div>
    </div>" : "") . "

    <div style='text-align: center; font-size: 1.4rem; margin-top: 15px; padding: 12px; background: {$cfg['colorBg']}; color: {$cfg['colorBoltCore']}; border: 1px solid {$cfg['colorAccent']}; border-radius: 6px; font-weight: bold;text-shadow: 0 0 10px {$cfg['colorPrimary']}, 0 0 20px {$cfg['colorPrimary']};' data-edit-keys='colorBg,colorBoltCore,colorAccent,colorPrimary'>
        {$cfg['labelTotal']} $total
    </div>
</div>";

// NEU: Log-Sperre für den Theme-Editor
$world = $input['world'] ?? 'Unbekannt';

$excludedWorlds = ["Theme-Editor", "Dashboard",'Dashboard-EyeCatcher', "Test-System", "Vorschau", "Test-Umgebung"];

if (!in_array($world, $excludedWorlds)) {

    // 1. IP Adresse ermitteln
    $remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (strpos($remoteIp, ',') !== false) $remoteIp = explode(',', $remoteIp)[0];

    // 1.1 IP Anonymisieren (Letztes Viertel maskieren)
    $anonIp = 'UNKNOWN';
    if ($remoteIp !== 'UNKNOWN') {
        if (filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: 192.168.178.10 -> 192.168.178.xxx
            $anonIp = preg_replace('/[0-9]+$/', 'xxx', $remoteIp);
        } elseif (filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: 2001:0db8:85a3:0000:0000:8a2e:0370:7334 -> 2001:0db8:85a3:0000:xxxx:xxxx:xxxx:xxxx
            $parts = explode(':', $remoteIp);
            $anonIp = implode(':', array_slice($parts, 0, 4)) . ':xxxx:xxxx:xxxx:xxxx';
        }
    }

    // 2. Daten vorbereiten
    $date = date('Y-m-d H:i:s');
    $name = ($input['actorName'] ?? 'Unbekannt') . " (" . ($input['playerName'] ?? 'Spieler') . ")";
    $points = $total . " Pkt"; // $total wurde bereits im Script berechnet
    $cards = "IDs: " . implode(',', array_column($hand, 'id'));
    $handIds = implode(',', array_column($hand, 'id'));
    $owned   = implode(',', $input['ownedCards'] ?? []);
    // NEU: URL, Welt und Version kombinieren
    $url      = $input['url'] ?? 'Keine-URL';

    $version  = $input['version'] ?? '?';
    $sysInfo  = "$world [$version] | $url";

    // 3. Zeile mit fester Breite formatieren
    // IP (15) | Datum (19) | Name (30) | Punkte (10) | Karten
    $logLine = sprintf(
        "%-19s | %-15s | %-80s | %-40s | %-8s | Hand: %-15s | Sammelkarten: %s\n",
        $date,
        substr($anonIp, 0, 15), 
        substr($sysInfo, 0, 80),
        substr($name, 0, 40),
        $points,
        substr($handIds, 0, 15),
        $owned,
        $cards
    );

    // 4. In Datei schreiben (FILE_APPEND erstellt die Datei, falls nicht vorhanden)
    file_put_contents('snatchlog.txt', $logLine, FILE_APPEND);
}
// --- ENDE LOG FUNKTION ---

echo json_encode(["html" => $html]);

