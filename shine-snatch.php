<?php
date_default_timezone_set('Europe/Berlin');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$config = require 'config.php';
$currentVersion = $config['current_version'];

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(["error" => "No input"]));

$clientVersion = $input['scriptVersion'] ?? "1.0";
$gamedatafile = 'game_data.json';
$game_data = json_decode(file_get_contents($gamedatafile), true);
$themes = json_decode(file_get_contents('themes.json'), true);

$response = [];
$updateWarningHtml = "";

// Prüfen, ob Version veraltet ist
if (version_compare($clientVersion, $currentVersion, '<')) {
    $response['updateAvailable'] = true;
    $response['newVersion'] = $currentVersion;

    // HTML-Hinweis für die Spielkarte vorbereiten
    $updateWarningHtml = "
    <div style='background: linear-gradient(45deg, #f5780b, #d97706); color: white; padding: 5px; border-radius: 4px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold; border: 1px solid #78350f; box-shadow: 0 2px 4px rgba(0,0,0,0.3);'>
        ⚠️ UPDATE VERFÜGBAR: v$currentVersion<br>
        <span style='font-weight: normal; font-size: 0.9em;'>Dein Makro ist veraltet (v$clientVersion)</span>
    </div>";
}

/**
 * Kernfunktion für die Theme-Verwaltung
 */
function getFinalThemeConfig($themeInput, $bestComboTheme, $themes, $actorName = null) {
    $presetsFile = 'user_presets.json';
    $themeInput = trim((string)$themeInput);
    $themeMapLower = [];
    foreach ($themes as $key => $val) { $themeMapLower[mb_strtolower((string)$key)] = $key; }
    $allKeys = array_keys($themes);

    // --- NEU: SPEICHER-LOGIK (set:) ---
    if (str_starts_with(strtolower($themeInput), 'set:')) {
        // Den eigentlichen Wert nach "set:" extrahieren
        $valueToSave = trim(substr($themeInput, 4));
        
        if (!empty($actorName)) {
            // Presets laden
            $userPresets = [];
            if (file_exists($presetsFile)) {
                $userPresets = json_decode(@file_get_contents($presetsFile), true) ?? [];
            }
            
            // Wenn der Wert leer ist (nur "set:"), lösche das Preset
            if ($valueToSave === '') {
                unset($userPresets[$actorName]);
            } else {
                $userPresets[$actorName] = $valueToSave;
            }
            
            // Speichern
            @file_put_contents($presetsFile, json_encode($userPresets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        // Nach dem Speichern arbeiten wir mit dem extrahierten Wert weiter
        $themeInput = $valueToSave;
    }

    // --- AB HIER DEINE 5-PUNKTE-LOGIK ---

    // 1. Wenn kombo-theme aktiv ist UND eine Kombination gefunden wurde
    $isKomboRequested = str_contains(strtolower($themeInput), 'kombo-theme');
    if ($isKomboRequested && !empty($bestComboTheme)) {
        if (isset($themes[$bestComboTheme])) {
            return ["cfg" => $themes[$bestComboTheme], "key" => $bestComboTheme, "mode" => "kombo"];
        }
    }

    // 2. Putzen: kombo-theme und Kommas entfernen
    $cleanTheme = trim(str_replace(['kombo-theme', ','], '', strtolower($themeInput)));

    // 3. Wenn jetzt noch "zufall" drin steht
    if ($cleanTheme === 'zufall') {
        $randKey = $allKeys[array_rand($allKeys)];
        return ["cfg" => $themes[$randKey], "key" => $randKey, "mode" => "zufall"];
    }

    // 4. Wenn ein spezifisches Theme gesetzt ist
    if (!empty($cleanTheme) && isset($themeMapLower[$cleanTheme])) {
        $finalKey = $themeMapLower[$cleanTheme];
        return ["cfg" => $themes[$finalKey], "key" => $finalKey, "mode" => "fixed"];
    }

    // 5. Fallback: Gold
    $defaultKey = $themeMapLower[$config['default_theme']] ?? $allKeys[0];
    return ["cfg" => $themes[$defaultKey], "key" => $defaultKey, "mode" => "default"];
}

// --- 2. DECK & KARTEN ---
$cardDist = $game_data['cardTypes'];
$deck = [];
foreach ($cardDist as $c) {
    for ($i = 0; $i < $c['count']; $i++) {
        $deck[] = ["name" => $c['name'], "points" => $c['points'], "id" => $c['startId'] + $i, "emoji" => $c['emoji']];
    }
}

if (isset($input['overrideHand']) && is_array($input['overrideHand'])) {
    $hand = [];
    foreach ($input['overrideHand'] as $searchId) {
        foreach ($deck as $card) { if ($card['id'] == $searchId) { $hand[] = $card; break; } }
    }
} else {
    shuffle($deck);
    $hand = array_slice($deck, 0, 5);
}

// --- 3. WÜRFELN & SYNERGIEN ---
$specBonusTotal = 0;
$specHits = [];
foreach ($hand as $card) {
    if (in_array($card['id'], $input['ownedCards'] ?? [])) {
        $roll = rand(1, 4); $specBonusTotal += $roll;
        $specHits[] = "#{$card['id']} ($roll)";
    }
}

$nameToId = [];
foreach ($game_data['cardTypes'] as $ct) { $nameToId[$ct['name']] = $ct['id']; }

$combos = []; 
foreach ($game_data['combos'] as $c) {
    $needed = array_count_values($c['needs']);
    $isPossible = true; $tempMatchedIndices = []; $matchedIds = [];
    foreach ($needed as $reqId => $amount) {
        $foundCount = 0;
        foreach ($hand as $idx => $card) {
            if (in_array($idx, $tempMatchedIndices)) continue;
            $currentCardId = $nameToId[$card['name']] ?? null;
            $isGroupMatch = false;
            foreach ($game_data['groups'] as $g) { if ($g['id'] === $reqId && in_array($currentCardId, $g['cards'])) { $isGroupMatch = true; break; } }
            if ($currentCardId === $reqId || $isGroupMatch) {
                $tempMatchedIndices[] = $idx; $matchedIds[] = $card['id']; $foundCount++;
                if ($foundCount >= $amount) break;
            }
        }
        if ($foundCount < $amount) { $isPossible = false; break; }
    }
    if ($isPossible) {
        $combos[] = ['label' => $c['emoji'] . " " . $c['name'], 'points' => $c['points'], 'class' => $c['cat'], 'indices' => $tempMatchedIndices, 'ids' => $matchedIds];
    }
}

usort($combos, function($a, $b) { return $b['points'] <=> $a['points']; });

// --- 4. OPTIMIERUNG & BESTE KOMBO FINDEN ---
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

// JETZT erst wissen wir, welches die beste Combo ist!
$highestComboTheme = (!empty($opt['combos'])) ? $opt['combos'][0]['class'] : null;

// --- 5. THEME AUSWÄHLEN ---
if (($input['theme'] ?? '') === "PREVIEW_MODE" && isset($input['customConfig'])) {
    // 1. FALL: Der Theme-Editor schickt eine direkte Konfiguration
    $cfg = $input['customConfig'];
    $finalKey = "Vorschau";
    $isKomboMode = false; 
} else {
    // 2. FALL: Normaler Spiel-Ablauf
    $rawThemeInput = isset($input['theme']) ? trim((string)$input['theme']) : '';
    $actorName = $input['actorName'] ?? null;
    $presetsFile = 'user_presets.json';

    // Preset-Logik: Nur laden, wenn der Input wirklich leer ist
    if ($rawThemeInput === '' && !empty($actorName)) {
        if (file_exists($presetsFile)) {
            $userPresets = json_decode(@file_get_contents($presetsFile), true) ?? [];
            $rawThemeInput = $userPresets[$actorName] ?? '';
        }
    }

    // Deine neue 5-Punkte-Funktion aufrufen
    $themeResult = getFinalThemeConfig($rawThemeInput, $highestComboTheme, $themes, $actorName);
    
    $cfg = $themeResult['cfg'];
    $finalKey = $themeResult['key'];
    // Falls du isKomboMode im HTML/Log brauchst:
    $isKomboMode = ($themeResult['mode'] === "kombo");
}

// --- 6. HTML GENERIERUNG (Vollständige Version) ---
$base = array_sum(array_column($hand, 'points'));
$subTotal = $base + $specBonusTotal;

$isOverridden = isset($input['overrideHand']) && is_array($input['overrideHand']);
$overrideWarning = "";

if ($isOverridden) {
    $overrideWarning = "
    <div style='background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 4px 8px; border-radius: 4px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;'>
        ⚠️ MANUELLE HAND (TESTMODUS / DEBUG)
    </div>";
}

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
        🃏{$cfg['headerIcon']} <span style='font-weight: bold;'>{$cfg['headerTitle']}</span>
    </h2>
    $overrideWarning
    $updateWarningHtml
    <p style='margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorAccent']};'  data-edit-keys='colorAccent'>{$cfg['labelHand']}</p>
    <ul style='list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid #333; border-radius: 4px; background: {$cfg['colorBgCard']};' data-edit-keys='colorBgCard'>
        $listHtml
    </ul>
    
    <div style='text-align: right; font-size: 0.85em; color: {$cfg['colorTextMuted']}; margin-bottom: 5px; font-style: italic;'  data-edit-keys='colorTextMuted'>
        {$cfg['labelHandSum']} <strong style='color: {$cfg['colorBoltCore']};' data-edit-keys='colorBoltCore'>$base Pkt</strong>
    </div>

    " . ($specBonusTotal > 0 ? "
    <div style='padding: 5px; background: " . ($cfg['colorSpecialBg'] ?? 'rgba(74, 222, 128, 0.1)') . "; border: 1px solid {$cfg['colorPrimary']}; border-radius: 4px; margin-bottom: 10px; font-size: 0.9em;'  data-edit-keys='colorPrimary,colorSpecialBg'>
        <span style='color: {$cfg['colorPrimary']}; font-weight: bold;' data-edit-keys='colorPrimary'>{$cfg['labelSpecialBonus']}</span>
        <span style='float: right; color: {$cfg['colorBoltCore']}; font-weight: bold;'  data-edit-keys='colorBoltCore'>+$specBonusTotal Pkt</span>
        <div style='font-size: 0.9em; color: {$cfg['colorTextMain']}; opacity: 0.8;'  data-edit-keys='colorTextMain' >Gewürfelt: " . implode(", ", $specHits) . "</div>
    </div>
    <div style='text-align: right; font-size: 0.9em; color: {$cfg['colorTextMuted']}; border-top: 1px solid #333; margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;'  data-edit-keys='colorTextMain'>
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
    $overrideWarning
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
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
}
// --- ENDE LOG FUNKTION ---

// Füge das HTML zum bestehenden Response-Array hinzu
$response['html'] = $html;
echo json_encode($response);
