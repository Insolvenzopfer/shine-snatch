<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit(json_encode(["error" => "No input"]));

// 1. DATEN & THEME
if (($input['theme'] ?? '') === "PREVIEW_MODE" && isset($input['customConfig'])) {
    $cfg = $input['customConfig'];
} else {
    // Normaler Foundry-Modus: Theme aus Datei laden
    $themes = json_decode(file_get_contents('themes.json'), true);
    $cfg = $themes[$input['theme']] ?? array_values($themes)[0];
}

$cardDist = [
    ["name" => "Gem/Item", "count" => 7, "points" => 5, "startId" => 1, "emoji" => "💎"],
    ["name" => "Beast/Monster", "count" => 7, "points" => 5, "startId" => 8, "emoji" => "👾"],
    ["name" => "Place", "count" => 6, "points" => 5, "startId" => 15, "emoji" => "🏰"],
    ["name" => "Artisan/Trader", "count" => 6, "points" => 5, "startId" => 21, "emoji" => "👨‍🌾"],
    ["name" => "Mystic", "count" => 5, "points" => 10, "startId" => 27, "emoji" => "🔮"],
    ["name" => "Scout", "count" => 5, "points" => 10, "startId" => 32, "emoji" => "🏹"],
    ["name" => "Warrior", "count" => 5, "points" => 10, "startId" => 37, "emoji" => "⚔️"],
    ["name" => "Mage", "count" => 5, "points" => 10, "startId" => 42, "emoji" => "🧙"],
    ["name" => "Magical Creature", "count" => 4, "points" => 15, "startId" => 47, "emoji" => "🦄"],
    ["name" => "Prince/Princess", "count" => 4, "points" => 15, "startId" => 51, "emoji" => "👑"],
    ["name" => "King/Queen", "count" => 4, "points" => 20, "startId" => 55, "emoji" => "🤴"],
    ["name" => "Divine Being", "count" => 2, "points" => 30, "startId" => 59, "emoji" => "✨"]
];

// 2. Deck bauen (Muss auch immer laufen, damit wir Karten-Daten für die IDs haben!)
$deck = [];
foreach ($cardDist as $c) {
    for ($i = 0; $i < $c['count']; $i++) {
        $deck[] = [
            "name" => $c['name'], 
            "points" => $c['points'], 
            "id" => $c['startId'] + $i, 
            "emoji" => $c['emoji']
        ];
    }
}

if (isset($input['overrideHand']) && is_array($input['overrideHand'])) {
    $hand = [];
    foreach ($input['overrideHand'] as $searchId) {
        foreach ($deck as $card) {
            if ($card['id'] == $searchId) {
                $hand[] = $card;
                break;
            }
        }
    }
} else {
    // Nur shufflen, wenn wir wirklich zufällig ziehen wollen!
    if (is_array($deck)) {
        shuffle($deck);
        $hand = array_slice($deck, 0, 5);
    } else {
        $hand = []; // Fallback
    }
}

// 3. WÜRFELN (PHP rand statt Foundry Roll)
$specBonusTotal = 0;
$specHits = [];
foreach ($hand as $card) {
    if (in_array($card['id'], $input['ownedCards'] ?? [])) {
        $roll = rand(1, 4); // Der 1d4
        $specBonusTotal += $roll;
        $specHits[] = "#{$card['id']} ($roll)";
    }
}

// 4. SYNERGIE REGELN (Funktion muss Variablen lokal kennen)
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
if (($counts['Beast/Monster'] ?? 0) >= 5) addC("🐾🐾🐾 Bestienhorde (5)", 60, array_fill(0, 5, "Beast/Monster"), $hand, $combos);
if (($counts['Beast/Monster'] ?? 0) >= 4) addC("🐾🐾 Bestienhorde (4)", 30, array_fill(0, 4, "Beast/Monster"), $hand, $combos);
if (($counts['Beast/Monster'] ?? 0) >= 3) addC("🐾 Bestienhorde (3)", 15, array_fill(0, 3, "Beast/Monster"), $hand, $combos);

if (isset($counts['Gem/Item'])) {
    if (($counts['Place'] ?? 0) >= 4) addC("🗺️🗺️🗺️ Karte (4+)", 60, ["Gem/Item", "Place", "Place", "Place", "Place"], $hand, $combos);
    if (($counts['Place'] ?? 0) >= 3) addC("🗺️🗺️ Karte (3)", 30, ["Gem/Item", "Place", "Place", "Place"], $hand, $combos);
    if (($counts['Place'] ?? 0) >= 2) addC("🗺️ Karte (2)", 15, ["Gem/Item", "Place", "Place"], $hand, $combos);
}

addC("🏰 Großes Königreich", 40, ["King/Queen", "Prince/Princess", "Warrior", "Place"], $hand, $combos);
addC("🏘️ Kleines Königreich (K)", 30, ["King/Queen", "Warrior", "Place"], $hand, $combos);
addC("🏘️ Kleines Königreich (P)", 30, ["Prince/Princess", "Warrior", "Place"], $hand, $combos);
addC("🛡️ Abenteuergruppe", 50, ["Mage", "Warrior", "Scout", "Mystic"], $hand, $combos);
addC("⚖️ Markt", 20, ["Artisan/Trader", "Place", "Gem/Item"], $hand, $combos);
addC("🔭 Magierturm", 15, ["Mage", "Place"], $hand, $combos);
addC("⛩️💩 Holy Shit", 50, ["Divine Being", "Mystic", "Mystic"], $hand, $combos);
addC("⚔️ Zu den Waffen", 30, ["King/Queen", "Warrior", "Warrior"], $hand, $combos);
addC("🐉 Herde", 30, ["Magical Creature", "Gem/Item", "Gem/Item"], $hand, $combos);
addC("🦄 Entführte Prinzessin", 50, ["Magical Creature", "Prince/Princess", "Place"], $hand, $combos);

if (($counts['Artisan/Trader'] ?? 0) >= 2 && isset($counts['Gem/Item'])) {
    addC("🐪 Große Karawane", 50, ["Artisan/Trader", "Artisan/Trader", "Gem/Item", "Warrior"], $hand, $combos);
    addC("📦 Kleine Karawane", 20, ["Artisan/Trader", "Artisan/Trader", "Gem/Item"], $hand, $combos);
}
addC("🏕️ Waldläufer Patrouille", 15, ["Scout", "Place"], $hand, $combos);
addC("🏮 Schrein", 15, ["Mystic", "Place"], $hand, $combos);
addC("🔨 Schmiede", 35, ["Warrior", "Gem/Item", "Artisan/Trader"], $hand, $combos);
addC("🌌 Sphärenentfaltung", 40, ["Divine Being", "Divine Being"], $hand, $combos);
addC("🐺 Rudelsführer", 35, ["Magical Creature", "Beast/Monster", "Beast/Monster"], $hand, $combos);
addC("🛡️ Paladine", 25, ["Mystic", "Warrior", "Warrior"], $hand, $combos);
addC("🍃 Druide", 25, ["Mystic", "Scout", "Beast/Monster"], $hand, $combos);
addC("🧪 Für die Wissenschaft", 35, ["Mage", "Magical Creature", "Gem/Item"], $hand, $combos);
addC("👪 Königsfamilie", 45, ["King/Queen", "King/Queen", "Prince/Princess", "Prince/Princess"], $hand, $combos);
addC("🔱 Heilige Dreifaltigkeit", 35, ["Divine Being", "Magical Creature", "Mystic"], $hand, $combos);

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
    
    $si = $isSpecial ? "<span style='color:{$cfg['colorPrimary']}; animation:blink 1s infinite'>{$cfg['specialCardEmoji']}</span>" : "";
    
    $listHtml .= "
    <li style='border-bottom: 1px solid #333; padding: 2px 0; list-style: none;'>
        $si <span style='$st'>{$c['emoji']} {$c['name']}</span> 
        <small style='color:{$cfg['colorAccent']}; opacity:0.8;'>#{$c['id']}</small>
        <span style='float:right; color:{$cfg['colorTextMain']}'>{$c['points']} Pkt</span>
    </li>";
}

// Aktive Synergien
$activeHtml = "";
foreach($opt['combos'] as $c) {
    $activeHtml .= "
    <div style='color:{$cfg['colorBoltCore']}; margin-bottom: 2px; text-shadow: 0 0 0px " . ($cfg['colorGlowMain'] ?? 'transparent') . ";'>
        {$cfg['iconCombo']} {$c['label']} 
        <small style='color:{$cfg['colorTextComboIds']}; font-size: 0.8em;'>(#".implode(", ", $c['ids']).")</small> 
        <span style='float:right; color:{$cfg['colorPrimary']};'>+{$c['points']}</span>
    </div>";
}

// Verfallene Pfade (Nicht genutzte Kombis)
$unusedHtml = "";
$unusedCombos = array_filter($combos, function($c) use ($opt) {
    return !in_array($c, $opt['combos']);
});

foreach ($unusedCombos as $c) {
    $unusedHtml .= "
    <div style='color: {$cfg['colorTextMuted']}; font-size: 0.85em; margin-bottom: 1px;'>
        {$cfg['iconUnused']} {$c['label']} <span style='float:right; opacity: 0.8;'>+{$c['points']}</span>
    </div>";
}

// --- 7. FINALES TEMPLATE ---
$total = $subTotal + $opt['pts'];

$html = "
<div style='font-family: \"Signika\", sans-serif; border: 2px solid {$cfg['colorAccent']}; border-radius: 10px; background: {$cfg['colorBg']}; padding: 12px; color: {$cfg['colorTextMain']};'>
    <h2 style='border-bottom: 2px solid {$cfg['colorPrimary']}; margin-top: 0; text-align: center; color: {$cfg['colorBoltCore']}; text-transform: uppercase;'>
        {$cfg['headerIcon']} {$cfg['headerTitle']}
    </h2>
    
    <p style='margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorAccent']};'>{$cfg['labelHand']}</p>
    <ul style='list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid #333; border-radius: 4px; background: {$cfg['colorBgCard']};'>
        $listHtml
    </ul>
    
    <div style='text-align: right; font-size: 0.85em; color: {$cfg['colorTextMuted']}; margin-bottom: 5px;'>
        {$cfg['labelHandSum']} <strong style='color: {$cfg['colorBoltCore']};'>$base Pkt</strong>
    </div>

    " . ($specBonusTotal > 0 ? "
    <div style='padding: 5px; background: " . ($cfg['colorSpecialBg'] ?? 'rgba(74, 222, 128, 0.1)') . "; border: 1px solid {$cfg['colorPrimary']}; border-radius: 4px; margin-bottom: 10px; font-size: 0.85em;'>
        <span style='color: {$cfg['colorPrimary']}; font-weight: bold;'>{$cfg['labelSpecialBonus']}</span>
        <span style='float: right; color: {$cfg['colorBoltCore']}; font-weight: bold;'>+$specBonusTotal Pkt</span>
        <div style='font-size: 0.75em; color: {$cfg['colorTextMain']}; opacity: 0.8;'>Gewürfelt: " . implode(", ", $specHits) . "</div>
    </div>
    <div style='text-align: right; font-size: 0.9em; color: {$cfg['colorTextMain']}; border-top: 1px solid #333; margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;'>
        {$cfg['labelSubTotal']} <strong style='color: {$cfg['colorBoltCore']};'>$subTotal Pkt</strong>
    </div>" : "") . "

    <div>
        <p style='margin: 0 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorAccent']};'>{$cfg['labelCombos']}</p>
        <div style='padding: 8px; background: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid {$cfg['colorPrimary']};'>
            " . ($activeHtml ?: "<i style='color: {$cfg['colorTextMuted']};'>Keine Synergien...</i>") . "
        </div>
    </div>

    " . ($unusedHtml ? "
    <div style='margin-top: 10px; opacity: 0.7;'>
        <p style='margin: 0 0 2px 0; font-size: 0.7em; font-weight: bold; text-transform: uppercase; color: {$cfg['colorTextMuted']};'>{$cfg['labelUnused']}</p>
        <div style='padding: 4px 8px; border-left: 2px solid {$cfg['colorTextMuted']};'>
            $unusedHtml
        </div>
    </div>" : "") . "

    <div style='text-align: center; font-size: 1.4em; margin-top: 15px; padding: 12px; background: {$cfg['colorBg']}; color: {$cfg['colorBoltCore']}; border: 1px solid {$cfg['colorAccent']}; border-radius: 6px; font-weight: bold;'>
        {$cfg['labelTotal']} $total
    </div>
</div>";

echo json_encode(["html" => $html]);