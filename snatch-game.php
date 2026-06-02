<?php
date_default_timezone_set("Europe/Berlin");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$config = require "config.php";
$currentVersion = $config["current_version"];

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    exit(json_encode(["error" => "No input"]));
}
file_put_contents(
    "data-old/debug_payload.json",
    json_encode($input, JSON_PRETTY_PRINT),
);

// Zentrale DB-Verbindung laden
require_once "db.php";
$pdo = getDatabaseConnection();

$clientVersion = $input["scriptVersion"] ?? "1.0";
$response = [];
$updateWarningHtml = "";

if (version_compare($clientVersion, $currentVersion, "<")) {
    $response["updateAvailable"] = true;
    $response["newVersion"] = $currentVersion;
    // Direktes Inline-Style für die Update-Warnung
    $updateWarningHtml = "
    <div style='background: linear-gradient(45deg, #f5780b, #d97706); color: white; padding: 5px; border-radius: 4px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold; border: 1px solid #78350f; box-shadow: 0 2px 4px rgba(0,0,0,0.3);'>
        ⚠️ UPDATE VERFÜGBAR: v$currentVersion<br>
        <span style='font-weight: normal; font-size: 0.9em;'>Dein Makro ist veraltet (v$clientVersion)</span>
    </div>";
}

/**
 * Kernfunktion für die Theme-Verwaltung (Aus SQL-Datenbank)
 */
function getFinalThemeConfig($themeInput, $bestComboTheme, $pdo)
{
    global $config;

    // 1. Input-Bereinigung (wie gehabt)
    if (is_array($themeInput)) {
        if (empty($themeInput)) {
            $themeInput = "";
        } elseif (
            isset($themeInput[0]) &&
            is_array($themeInput[0]) &&
            array_key_exists("id", $themeInput[0])
        ) {
            $themeInput = implode(",", array_column($themeInput, "id"));
        } else {
            $flat = [];
            foreach ($themeInput as $el) {
                if (!is_array($el) && !is_object($el)) {
                    $flat[] = trim((string) $el);
                }
            }
            $themeInput = implode(",", $flat);
        }
    } else {
        $themeInput = trim((string) $themeInput);
    }

    // 2. Themes aus der DB laden
    $stmt = $pdo->query("SELECT * FROM snatch_themes");
    $dbThemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $themes = [];
    foreach ($dbThemes as $t) {
        $themes[$t["theme_name"]] = $t;
    }

    $themeMapLower = [];
    foreach ($themes as $key => $val) {
        $themeMapLower[mb_strtolower((string) $key)] = $key;
    }
    $allKeys = array_keys($themes);

    // 3. Fallback: Kombo-Theme abfangen
    if (
        str_contains(strtolower($themeInput), "kombo-theme") &&
        !empty($bestComboTheme)
    ) {
        if (isset($themes[$bestComboTheme])) {
            return [
                "cfg" => $themes[$bestComboTheme],
                "key" => $bestComboTheme,
                "mode" => "kombo",
            ];
        }
    }

    // 4. Komma-separierte Liste prüfen (Sowohl mit als auch ohne "kombo-theme")
    // Wir reinigen den Input für die Einzel-Checks
    $cleanThemeInput = trim(
        str_replace("kombo-theme", "", strtolower($themeInput)),
    );
    // Falls durch das Entfernen von kombo-theme führende/folgende Kommas blieben, säubern
    $cleanThemeInput = trim($cleanThemeInput, ",");

    if (str_contains($cleanThemeInput, ",")) {
        $possibleThemes = explode(",", $cleanThemeInput);
        $validChoices = [];

        foreach ($possibleThemes as $rawChoice) {
            $choice = trim($rawChoice);
            if (isset($themeMapLower[$choice])) {
                $validChoices[] = $themeMapLower[$choice];
            }
        }

        // Wenn gültige Wunsch-Themes gefunden wurden, würfeln!
        if (!empty($validChoices)) {
            $chosenKey = $validChoices[array_rand($validChoices)];
            return [
                "cfg" => $themes[$chosenKey],
                "key" => $chosenKey,
                "mode" => "wunsch_zufall",
            ];
        }
    }

    // 5. Einzelnes Theme oder "zufall" (wie gehabt)
    $cleanTheme = str_replace(",", "", $cleanThemeInput);

    if ($cleanTheme === "zufall" && !empty($allKeys)) {
        $randKey = $allKeys[array_rand($allKeys)];
        return [
            "cfg" => $themes[$randKey],
            "key" => $randKey,
            "mode" => "zufall",
        ];
    }

    if (!empty($cleanTheme) && isset($themeMapLower[$cleanTheme])) {
        $finalKey = $themeMapLower[$cleanTheme];
        return [
            "cfg" => $themes[$finalKey],
            "key" => $finalKey,
            "mode" => "fixed",
        ];
    }

    // Default Fallback
    $defaultKey =
        $themeMapLower[strtolower($config["default_theme"] ?? "gold")] ??
        ($allKeys[0] ?? null);
    return [
        "cfg" => $themes[$defaultKey],
        "key" => $defaultKey,
        "mode" => "default",
    ];
}

// --- DECK & KARTEN AUS SQL GENERIEREN ---
$stmt = $pdo->query(
    "SELECT id, name, count, points, start_id, emoji FROM snatch_game_card_types",
);
$cardDist = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deck = [];
$nameToId = [];

foreach ($cardDist as $c) {
    $nameToId[$c["id"]] = (int) $c["start_id"];

    for ($i = 0; $i < $c["count"]; $i++) {
        $cardId = (int) $c["start_id"] + $i;
        $deck[] = [
            "name" => $c["id"],
            "full_name" => $c["name"],
            "points" => (int) $c["points"],
            "id" => $cardId,
            "emoji" => $c["emoji"],
        ];
    }
}

// 1. Gruppen direkt aus der Tabelle `snatch_game_groups` laden
$stmtGroups = $pdo->query("SELECT id, cards FROM snatch_game_groups");
$dbGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
foreach ($dbGroups as $g) {
    $groups[] = [
        "id" => $g["id"],
        "kuerzel" => json_decode($g["cards"], true) ?? [],
    ];
}

// 2. Hand ermitteln
if (isset($input["overrideHand"]) && is_array($input["overrideHand"])) {
    $hand = [];
    $tempDeck = $deck;
    foreach ($input["overrideHand"] as $searchId) {
        foreach ($tempDeck as $deckIdx => $card) {
            if ($card["id"] == $searchId) {
                $hand[] = $card;
                unset($tempDeck[$deckIdx]);
                break;
            }
        }
    }
    if (count($hand) < 5) {
        shuffle($deck);
        $hand = array_slice($deck, 0, 5);
    }
} else {
    shuffle($deck);
    $hand = array_slice($deck, 0, 5);
}

// --- WÜRFELN & SYNERGIEN (SAMMLERKARTEN-BONUS) ---
$ownedCardIds = is_array($input["ownedCards"] ?? [])
    ? array_column($input["ownedCards"], "id")
    : [];

$specBonusTotal = 0;
$specHits = [];

foreach ($hand as $card) {
    if (in_array($card["id"], $ownedCardIds)) {
        $roll = rand(1, 4);
        $specBonusTotal += $roll;
        $specHits[] = "{$card["emoji"]} #{$card["id"]} ($roll)";
    }
}

// Combos aus SQL laden
$stmtCombos = $pdo->query(
    "SELECT name, emoji, points, needs, cat FROM snatch_game_combos",
);
$dbCombos = $stmtCombos->fetchAll(PDO::FETCH_ASSOC);

$combos = [];
foreach ($dbCombos as $c) {
    $needsArray = json_decode($c["needs"], true) ?? [];
    $needed = array_count_values($needsArray);
    $isPossible = true;
    $tempMatchedIndices = [];
    $matchedIds = [];

    foreach ($needed as $reqId => $amount) {
        $foundCount = 0;

        foreach ($hand as $idx => $card) {
            if (in_array($idx, $tempMatchedIndices)) {
                continue;
            }

            $currentCardKuerzel = $card["name"];
            $isMatch = false;

            if (strlen($reqId) > 3) {
                foreach ($groups as $g) {
                    if (strval($g["id"]) === strval($reqId)) {
                        if (in_array($currentCardKuerzel, $g["kuerzel"])) {
                            $isMatch = true;
                            break;
                        }
                    }
                }
            } else {
                if (strval($currentCardKuerzel) === strval($reqId)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $tempMatchedIndices[] = $idx;
                $matchedIds[] = $card["id"];
                $foundCount++;

                if ($foundCount >= $amount) {
                    break;
                }
            }
        }

        if ($foundCount < $amount) {
            $isPossible = false;
            break;
        }
    }

    if ($isPossible) {
        $combos[] = [
            "label" => $c["emoji"] . " " . $c["name"],
            "points" => (int) $c["points"],
            "class" => $c["cat"],
            "indices" => $tempMatchedIndices,
            "ids" => $matchedIds,
        ];
    }
}

usort($combos, function ($a, $b) {
    return $b["points"] <=> $a["points"];
});

function findBest($combos, $used = [])
{
    $best = ["combos" => [], "pts" => 0];
    foreach ($combos as $i => $c) {
        if (!array_intersect($c["indices"], $used)) {
            $res = findBest(
                array_slice($combos, $i + 1),
                array_merge($used, $c["indices"]),
            );
            if ($c["points"] + $res["pts"] > $best["pts"]) {
                $best["pts"] = $c["points"] + $res["pts"];
                $best["combos"] = array_merge([$c], $res["combos"]);
            }
        }
    }
    return $best;
}

$opt = findBest($combos);
$usedIdx = array_merge(...array_column($opt["combos"], "indices") ?: [[]]);
$highestComboTheme = !empty($opt["combos"]) ? $opt["combos"][0]["class"] : null;

// --- THEME AUSWÄHLEN VIA SQL ---
if (
    ($input["theme"] ?? "") === "PREVIEW_MODE" &&
    isset($input["customConfig"])
) {
    $rawCfg = $input["customConfig"];
    $cfg = [];

    foreach ($rawCfg as $key => $value) {
        $snakeKey = strtolower(preg_replace("/(?<!^)[A-Z]/", '_$0', $key));
        $cfg[$snakeKey] = $value;
    }

    if (isset($rawCfg["headerIcon"])) {
        $cfg["header_icon"] = $rawCfg["headerIcon"];
    }
    if (isset($rawCfg["headerTitle"])) {
        $cfg["header_title"] = $rawCfg["headerTitle"];
    }
    if (isset($rawCfg["specialCardEmoji"])) {
        $cfg["special_card_emoji"] = $rawCfg["specialCardEmoji"];
    }
    if (isset($rawCfg["labelHand"])) {
        $cfg["label_hand"] = $rawCfg["labelHand"];
    }
    if (isset($rawCfg["labelHandSum"])) {
        $cfg["label_hand_sum"] = $rawCfg["labelHandSum"];
    }
    if (isset($rawCfg["labelSpecialBonus"])) {
        $cfg["label_special_bonus"] = $rawCfg["labelSpecialBonus"];
    }
    if (isset($rawCfg["labelSubTotal"])) {
        $cfg["label_sub_total"] = $rawCfg["labelSubTotal"];
    }
    if (isset($rawCfg["labelCombos"])) {
        $cfg["label_combos"] = $rawCfg["labelCombos"];
    }
    if (isset($rawCfg["iconCombo"])) {
        $cfg["icon_combo"] = $rawCfg["iconCombo"];
    }
    if (isset($rawCfg["labelUnused"])) {
        $cfg["label_unused"] = $rawCfg["labelUnused"];
    }
    if (isset($rawCfg["iconUnused"])) {
        $cfg["icon_unused"] = $rawCfg["iconUnused"];
    }
    if (isset($rawCfg["labelTotal"])) {
        $cfg["label_total"] = $rawCfg["labelTotal"];
    }
    if (isset($rawCfg["shadowColor"])) {
        $cfg["shadow_color"] = $rawCfg["shadowColor"];
    }

    $finalKey = "Vorschau";
    $isKomboMode = false;
} else {
    $rawThemeInput = isset($input["theme"])
        ? trim((string) $input["theme"])
        : "";
    $themeResult = getFinalThemeConfig(
        $rawThemeInput,
        $highestComboTheme,
        $pdo,
    );
    $cfg = $themeResult["cfg"];
}

// --- HTML GENERIERUNG ---
$base = array_sum(array_column($hand, "points"));
$subTotal = $base + $specBonusTotal;
$total = $subTotal + $opt["pts"];

$isOverridden =
    isset($input["overrideHand"]) && is_array($input["overrideHand"]);
$overrideWarning = $isOverridden
    ? "<div style='background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 4px 8px; border-radius: 4px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;'>⚠️ MANUELLE HAND (TESTMODUS)</div>"
    : "";

// --- ZENTRALE STYLE-VARIABLEN (Ersatz für den Style-Block für Foundry VTT) ---
$s_container = "display: block; box-sizing: border-box; width: 100%; font-family: 'Signika', sans-serif; border: 2px solid {$cfg["color_accent"]}; border-radius: 10px; background-color: {$cfg["color_bg"]}; padding: 12px; color: {$cfg["color_text_main"]}; box-shadow: 0 6px 12px {$cfg["shadow_color"]};";
$s_header = "border-bottom: 2px solid {$cfg["color_primary"]}; margin-top: 0; text-align: center; color: {$cfg["color_bolt_core"]}; text-transform: uppercase;";
$s_header_title = "font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";
$s_section_label = "margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_accent"]};";
$s_card_list = "list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid #333; border-radius: 4px; background-color: {$cfg["color_bg_card"]};";
$s_card_item =
    "border-bottom: 1px solid #333; padding: 2px 0; list-style: none;";
$s_special_marker = "color: {$cfg["color_primary"]}; font-weight: bold; margin-right: 4px;";
$s_card_text_used = "color: {$cfg["color_text_muted"]}; text-decoration: line-through;";
$s_card_text_unused = "font-weight: bold; color: {$cfg["color_bolt_core"]};";
$s_card_id = "color: {$cfg["color_accent"]}; opacity: 0.8;";
$s_points_right = "float: right; color: {$cfg["color_text_main"]};";
$s_sum_block = "text-align: right; font-size: 0.85em; color: {$cfg["color_text_muted"]}; margin-bottom: 5px; font-style: italic;";
$s_bold_bolt = "color: {$cfg["color_bolt_core"]};";
$s_subtotal_block = "text-align: right; font-size: 0.9em; color: {$cfg["color_text_muted"]}; border-top: 1px solid #333; margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;";
$s_bonus_box =
    "padding: 5px; background-color: " .
    ($cfg["color_special_bg"] ?? "rgba(74, 222, 128, 0.1)") .
    "; border: 1px solid {$cfg["color_primary"]}; border-radius: 4px; margin-bottom: 10px; font-size: 0.9em;";
$s_bonus_title = "color: {$cfg["color_primary"]}; font-weight: bold;";
$s_bonus_pts = "float: right; color: {$cfg["color_bolt_core"]}; font-weight: bold;";
$s_bonus_detail = "font-size: 0.9em; color: {$cfg["color_text_main"]}; opacity: 0.8;";
$s_combo_container = "padding: 8px; background-color: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid {$cfg["color_primary"]};";
$s_combo_item = "color: {$cfg["color_bolt_core"]}; margin-bottom: 2px;";
$s_combo_ids = "color: {$cfg["color_text_combo_ids"]}; font-size: 0.8em;";
$s_points_right_p = "float: right; color: {$cfg["color_primary"]};";
$s_unused_wrapper = "margin-top: 10px; opacity: 0.7;";
$s_unused_container = "padding: 4px 8px; border-left: 2px solid {$cfg["color_text_muted"]};";
$s_unused_item = "color: {$cfg["color_text_muted"]}; font-size: 0.9em; margin-bottom: 1px;";
$s_total_box = "text-align: center; font-size: 1.4rem; margin-top: 15px; padding: 12px; background-color: {$cfg["color_bg"]}; color: {$cfg["color_bolt_core"]}; border: 1px solid {$cfg["color_accent"]}; border-radius: 6px; font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";

// Hand-Liste generieren
$listHtml = "";
foreach ($hand as $i => $c) {
    $isUsed = in_array($i, $usedIdx);
    $isSpecial = in_array($c["id"], $ownedCardIds);

    $textStyle = $isUsed ? $s_card_text_used : $s_card_text_unused;

    $si = $isSpecial
        ? "<span style=\"$s_special_marker\" data-edit-keys='colorPrimary'>{$cfg["special_card_emoji"]}</span>"
        : "";

    $listHtml .= "
                <li style=\"$s_card_item\">
                    $si <span style=\"$textStyle\" data-edit-keys='colorTextMuted,colorBoltCore'>{$c["emoji"]} {$c["full_name"]}</span>
                    <small style=\"$s_card_id\" data-edit-keys='colorAccent'>#{$c["id"]}</small>
                    <span style=\"$s_points_right\" data-edit-keys='colorTextMain'>{$c["points"]} Pkt</span>
                </li>";
}

// Aktive Synergien generieren
$activeHtml = "";
foreach ($opt["combos"] as $c) {
    $activeHtml .=
        "
            <div style=\"$s_combo_item\" data-edit-keys='colorBoltCore'>
                {$cfg["icon_combo"]} {$c["label"]}
                <small style=\"$s_combo_ids\" data-edit-keys='colorTextComboIds'>(#" .
        implode(", ", $c["ids"]) .
        ")</small>
                <span style=\"$s_points_right_p\" data-edit-keys='colorPrimary'>+{$c["points"]}</span>
            </div>";
}

// Verfallene Pfade generieren
$unusedHtml = "";
$unusedCombos = array_filter($combos, function ($c) use ($opt) {
    return !in_array($c, $opt["combos"]);
});

foreach ($unusedCombos as $c) {
    $unusedHtml .=
        "
            <div style=\"$s_unused_item\" data-edit-keys='colorTextMuted'>
                {$cfg["icon_unused"]} {$c["label"]}
                <small style='opacity: 0.8; font-size: 1em;'>(#" .
        implode(", ", $c["ids"]) .
        ")</small>
                <span style='float: right; opacity: 0.8;'>+{$c["points"]}</span>
            </div>";
}

// --- FINALES TEMPLATE (Jetzt mit korrekten double-quotes für Foundry) ---
$html =
    "
        <div style=\"$s_container\" data-edit-key='shadowColor,colorTextMain,colorBg,colorAccent'>
            <h2 style=\"$s_header\" data-edit-keys='colorPrimary,colorBoltCore'>
                <span style=\"$s_header_title\" data-edit-keys='colorPrimary'>📜{$cfg["header_icon"]} <span style=\"$s_header_title\" data-edit-keys='colorPrimary'>{$cfg["header_title"]}</span>
            </h2>
            $overrideWarning
            $updateWarningHtml
            <p style=\"$s_section_label\" data-edit-keys='colorAccent'>{$cfg["label_hand"]}</p>
            <ul style=\"$s_card_list\" data-edit-keys='colorBgCard'>
                $listHtml
            </ul>

            <div style=\"$s_sum_block\" data-edit-keys='colorTextMuted'>
                {$cfg["label_hand_sum"]} <strong style=\"$s_bold_bolt\" data-edit-keys='colorBoltCore'>$base Pkt</strong>
            </div>

            " .
    ($specBonusTotal > 0
        ? "
            <div style=\"$s_bonus_box\" data-edit-keys='colorPrimary,colorSpecialBg'>
                <span style=\"$s_bonus_title\" data-edit-keys='colorPrimary'>{$cfg["label_special_bonus"]}</span>
                <span style=\"$s_bonus_pts\" data-edit-keys='colorBoltCore'>+$specBonusTotal Pkt</span>
                <div style=\"$s_bonus_detail\" data-edit-keys='colorTextMain'>Gewürfelt: " .
            implode(", ", $specHits) .
            "</div>
            </div>
            <div style=\"$s_subtotal_block\" data-edit-keys='colorTextMain'>
                {$cfg["label_sub_total"]} <strong style=\"$s_bold_bolt\" data-edit-keys='colorBoltCore'>$subTotal Pkt</strong>
            </div>"
        : "") .
    "

            <div>
                <p style=\"margin: 0 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_accent"]};\" data-edit-keys='colorAccent'>{$cfg["label_combos"]}</p>
                <div style=\"$s_combo_container\" data-edit-keys='colorPrimary'>
                    " .
    ($activeHtml ?:
        "<i style=\"color: {$cfg["color_text_muted"]}; font-style: italic;\" data-edit-keys='colorTextMuted'>Keine Synergien...</i>") .
    "
                </div>
            </div>

            " .
    ($unusedHtml
        ? "
            <div style=\"$s_unused_wrapper\">
                <p style=\"margin: 0 0 2px 0; font-size: 0.9em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_text_muted"]};\" data-edit-keys='colorTextMuted'>{$cfg["label_unused"]}</p>
                <div style=\"$s_unused_container\" data-edit-keys='colorTextMuted'>
                    $unusedHtml
                </div>
            </div>"
        : "") .
    "

            <div style=\"$s_total_box\" data-edit-keys='colorBg,colorBoltCore,colorAccent,colorPrimary'>
                {$cfg["label_total"]} $total
            </div>
            $overrideWarning
        </div>";

$response["html"] = $html;
$response["total_points"] = $total;
$response["hand_ids"] = implode(",", array_column($hand, "id"));

echo json_encode($response);
