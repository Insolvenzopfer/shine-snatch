<?php
session_start();

// Prüfen ob Admin (Session ID gesetzt)
$is_logged_in = isset($_SESSION["loggedin"]);

$config = require "config.php";
$currentVersion = $config["current_version"] ?? "1.4";
$apiUrl = $config["api_url"] ?? "themes.php";

// Zentrale DB-Verbindung laden
require_once "db.php";
$pdo = getDatabaseConnection();

$colorFields = [
    "colorPrimary",
    "colorGlowMain",
    "colorBoltCore",
    "colorAccent",
    "colorBg",
    "colorBgCard",
    "shadowColor",
    "colorTextMain",
    "colorSpecialBg",
    "colorTextMuted",
    "colorTextComboIds",
];

$textFields = [
    "headerIcon",
    "headerTitle",
    "specialCardEmoji",
    "labelHand",
    "labelHandSum",
    "labelSpecialBonus",
    "labelSubTotal",
    "labelCombos",
    "iconCombo",
    "labelUnused",
    "iconUnused",
    "labelTotal",
];

// --- INITIALISIERUNG / FALLBACK (Falls DB komplett leer ist) ---
$stmtCheck = $pdo->query("SELECT COUNT(*) FROM snatch_themes");
if ($stmtCheck->fetchColumn() == 0) {
    $initialThemes = [
        "Gold" => [
            "colorPrimary" => "#daa520",
            "colorBoltCore" => "#ffffff",
            "colorGlowMain" => "rgba(218, 165, 20, 0.4)",
            "colorAccent" => "#daa520",
            "colorBg" => "#1a1c1a",
            "colorBgCard" => "rgba(255, 255, 255, 0.05)",
            "shadowColor" => "rgba(0, 0, 0, 0.6)",
            "colorTextMain" => "#eeeeee",
            "colorTextMuted" => "#666666",
            "colorTextComboIds" => "#aaaaaa",
            "headerIcon" => "🎴",
            "headerTitle" => "Shine-Snatch",
            "labelHand" => "Die Hand des Schicksals:",
            "labelHandSum" => "Basis-Wert:",
            "labelSubTotal" => "Zwischensumme:",
            "labelCombos" => "Aktive Synergien:",
            "iconCombo" => "✦",
            "labelUnused" => "Verfallene Pfade:",
            "iconUnused" => "✧",
            "labelTotal" => "TOTAL:",
            "specialCardEmoji" => "🌟",
            "labelSpecialBonus" => "Sammel-Bonus:",
            "colorSpecialBg" => "rgba(74, 222, 128, 0.1)",
        ],
    ];

    $stmtInsert = $pdo->prepare("INSERT INTO snatch_themes (
        theme_name, color_primary, color_glow_main, color_bolt_core, color_accent, color_bg, color_bg_card, shadow_color,
        color_text_main, color_special_bg, color_text_muted, color_text_combo_ids, header_icon, header_title,
        special_card_emoji, label_hand, label_hand_sum, label_special_bonus, label_sub_total, label_combos,
        icon_combo, label_unused, icon_unused, label_total
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($initialThemes as $name => $cfg) {
        $stmtInsert->execute([
            $name,
            $cfg["colorPrimary"],
            $cfg["colorGlowMain"],
            $cfg["colorBoltCore"],
            $cfg["colorAccent"],
            $cfg["colorBg"],
            $cfg["colorBgCard"],
            $cfg["shadowColor"],
            $cfg["colorTextMain"],
            $cfg["colorSpecialBg"],
            $cfg["colorTextMuted"],
            $cfg["colorTextComboIds"],
            $cfg["headerIcon"],
            $cfg["headerTitle"],
            $cfg["specialCardEmoji"],
            $cfg["labelHand"],
            $cfg["labelHandSum"],
            $cfg["labelSpecialBonus"],
            $cfg["labelSubTotal"],
            $cfg["labelCombos"],
            $cfg["iconCombo"],
            $cfg["labelUnused"],
            $cfg["iconUnused"],
            $cfg["labelTotal"],
        ]);
    }
}

// --- SPEICHERN (NUR ADMINS) ---
if (
    $is_logged_in &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "save"
) {
    $themeName = trim($_POST["themeName"] ?? "");
    $submittedConfig = $_POST["config"] ?? [];

    if (!empty($themeName)) {
        $sqlUpdate = "INSERT INTO snatch_themes (
            theme_name, color_primary, color_glow_main, color_bolt_core, color_accent, color_bg, color_bg_card, shadow_color,
            color_text_main, color_special_bg, color_text_muted, color_text_combo_ids, header_icon, header_title,
            special_card_emoji, label_hand, label_hand_sum, label_special_bonus, label_sub_total, label_combos,
            icon_combo, label_unused, icon_unused, label_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            color_primary=VALUES(color_primary), color_glow_main=VALUES(color_glow_main), color_bolt_core=VALUES(color_bolt_core),
            color_accent=VALUES(color_accent), color_bg=VALUES(color_bg), color_bg_card=VALUES(color_bg_card), shadow_color=VALUES(shadow_color),
            color_text_main=VALUES(color_text_main), color_special_bg=VALUES(color_special_bg), color_text_muted=VALUES(color_text_muted),
            color_text_combo_ids=VALUES(color_text_combo_ids), header_icon=VALUES(header_icon), header_title=VALUES(header_title),
            special_card_emoji=VALUES(special_card_emoji), label_hand=VALUES(label_hand), label_hand_sum=VALUES(label_hand_sum),
            label_special_bonus=VALUES(label_special_bonus), label_sub_total=VALUES(label_sub_total), label_combos=VALUES(label_combos),
            icon_combo=VALUES(icon_combo), label_unused=VALUES(label_unused), icon_unused=VALUES(icon_unused), label_total=VALUES(label_total)";

        $stmtSave = $pdo->prepare($sqlUpdate);
        $stmtSave->execute([
            $themeName,
            $submittedConfig["colorPrimary"] ?? "",
            $submittedConfig["colorGlowMain"] ?? "",
            $submittedConfig["colorBoltCore"] ?? "",
            $submittedConfig["colorAccent"] ?? "",
            $submittedConfig["colorBg"] ?? "",
            $submittedConfig["colorBgCard"] ?? "",
            $submittedConfig["shadowColor"] ?? "",
            $submittedConfig["colorTextMain"] ?? "",
            $submittedConfig["colorSpecialBg"] ?? "",
            $submittedConfig["colorTextMuted"] ?? "",
            $submittedConfig["colorTextComboIds"] ?? "",
            $submittedConfig["headerIcon"] ?? "",
            $submittedConfig["headerTitle"] ?? "",
            $submittedConfig["specialCardEmoji"] ?? "",
            $submittedConfig["labelHand"] ?? "",
            $submittedConfig["labelHandSum"] ?? "",
            $submittedConfig["labelSpecialBonus"] ?? "",
            $submittedConfig["labelSubTotal"] ?? "",
            $submittedConfig["labelCombos"] ?? "",
            $submittedConfig["iconCombo"] ?? "",
            $submittedConfig["labelUnused"] ?? "",
            $submittedConfig["iconUnused"] ?? "",
            $submittedConfig["labelTotal"] ?? "",
        ]);

        header(
            "Location: " .
                $_SERVER["PHP_SELF"] .
                "?theme=" .
                urlencode($themeName),
        );
        exit();
    }
}

// =========================================================================
// LOKALE BACKEND AJAX INTERFACE
// =========================================================================
if (
    (isset($_GET["action"]) && $_GET["action"] === "get_preview_html") ||
    (isset($_POST["action"]) && $_POST["action"] === "get_preview_html")
) {

    $themeNameAjax = $_GET["theme"] ?? ($_POST["theme"] ?? "default");
    $stmtAjax = $pdo->prepare(
        "SELECT * FROM snatch_themes WHERE theme_name = ?",
    );
    $stmtAjax->execute([$themeNameAjax]);
    $themeDataAjax = $stmtAjax->fetch(PDO::FETCH_ASSOC);

    if (!$themeDataAjax) {
        $stmtAjax = $pdo->query("SELECT * FROM snatch_themes LIMIT 1");
        $themeDataAjax = $stmtAjax->fetch(PDO::FETCH_ASSOC);
    }

    $ajaxConfig = [];
    if ($themeDataAjax) {
        $ajaxConfig["colorPrimary"] = $themeDataAjax["color_primary"];
        $ajaxConfig["colorGlowMain"] = $themeDataAjax["color_glow_main"];
        $ajaxConfig["colorBoltCore"] = $themeDataAjax["color_bolt_core"];
        $ajaxConfig["colorAccent"] = $themeDataAjax["color_accent"];
        $ajaxConfig["colorBg"] = $themeDataAjax["color_bg"];
        $ajaxConfig["colorBgCard"] = $themeDataAjax["color_bg_card"];
        $ajaxConfig["shadowColor"] = $themeDataAjax["shadow_color"];
        $ajaxConfig["colorTextMain"] = $themeDataAjax["color_text_main"];
        $ajaxConfig["colorSpecialBg"] = $themeDataAjax["color_special_bg"];
        $ajaxConfig["colorTextMuted"] = $themeDataAjax["color_text_muted"];
        $ajaxConfig["colorTextComboIds"] =
            $themeDataAjax["color_text_combo_ids"];
        $ajaxConfig["headerIcon"] = $themeDataAjax["header_icon"];
        $ajaxConfig["headerTitle"] = $themeDataAjax["header_title"];
        $ajaxConfig["labelHand"] = $themeDataAjax["label_hand"];
        $ajaxConfig["labelHandSum"] = $themeDataAjax["label_hand_sum"];
    }
    ?>
    <div style='font-family: "Signika", sans-serif; border: 2px solid <?= htmlspecialchars(
        $ajaxConfig["colorPrimary"] ?? "#11ab83",
    ) ?>; border-radius: 10px; background: <?= htmlspecialchars(
    $ajaxConfig["colorBg"] ?? "#121212",
) ?>; padding: 12px; color: <?= htmlspecialchars(
    $ajaxConfig["colorTextMain"] ?? "#eeeeee",
) ?>; box-shadow: 0 6px 12px <?= htmlspecialchars(
    $ajaxConfig["shadowColor"] ?? "rgba(0,0,0,0.6)",
) ?>;' data-edit-key='shadowColor,colorTextMain,colorBg,colorAccent'>
        <h2 style='border-bottom: 2px solid <?= htmlspecialchars(
            $ajaxConfig["colorPrimary"] ?? "#10b981",
        ) ?>; margin-top: 0; text-align: center; color: #ecfdf5; text-transform: uppercase;' data-edit-keys='colorPrimary,colorBoltCore,colorGlowMain'>
            <?= htmlspecialchars(
                $ajaxConfig["headerIcon"] ?? "🎴",
            ) ?> <span style='font-weight: bold;'><?= htmlspecialchars(
     $ajaxConfig["headerTitle"] ?? "Shine-Snatch",
 ) ?></span>
        </h2>
        <p style='margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: <?= htmlspecialchars(
            $ajaxConfig["colorAccent"] ?? "#daa520",
        ) ?>;' data-edit-keys='colorAccent'><?= htmlspecialchars(
    $ajaxConfig["labelHand"] ?? "Die Hand des Schicksals:",
) ?></p>
        <ul style='list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid #333; border-radius: 4px; background: <?= htmlspecialchars(
            $ajaxConfig["colorBgCard"] ?? "rgba(255,255,255,0.05)",
        ) ?>;' data-edit-keys='colorBgCard'>
            <li style='border-bottom: 1px solid #333; padding: 2px 0; list-style: none;'>
                 <span style='font-weight:bold; color:#ecfdf5;' data-edit-keys='colorTextMuted'>👾 Karte aus dem Spiel</span>
                <span style='float:right; color:<?= htmlspecialchars(
                    $ajaxConfig["colorTextMain"] ?? "#eee",
                ) ?>' data-edit-keys='colorTextMain'>10 Pkt</span>
            </li>
        </ul>
    </div>
    <?php exit();
}

// --- DATA FETCHING ---
$querySQL = "SELECT
    theme_name, color_primary AS colorPrimary, color_glow_main AS colorGlowMain, color_bolt_core AS colorBoltCore,
    color_accent AS colorAccent, color_bg AS colorBg, color_bg_card AS colorBgCard, shadow_color AS shadowColor,
    color_text_main AS colorTextMain, color_special_bg AS colorSpecialBg, color_text_muted AS colorTextMuted,
    color_text_combo_ids AS colorTextComboIds, header_icon AS headerIcon, header_title AS headerTitle,
    special_card_emoji AS specialCardEmoji, label_hand AS labelHand, label_hand_sum AS labelHandSum,
    label_special_bonus AS labelSpecialBonus, label_sub_total AS labelSubTotal, label_combos AS labelCombos,
    icon_combo AS iconCombo, label_unused AS labelUnused, icon_unused AS iconUnused, label_total AS labelTotal
FROM snatch_themes ORDER BY theme_name ASC";

$themes = [];
$dbThemes = $pdo->query($querySQL)->fetchAll(PDO::FETCH_ASSOC);
foreach ($dbThemes as $row) {
    $name = $row["theme_name"];
    unset($row["theme_name"]);
    $themes[$name] = $row;
}

$currentThemeName = $_GET["theme"] ?? (array_key_first($themes) ?? "Gold");
$currentConfig = $themes[$currentThemeName] ?? [];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Shine-Snatch <?= $is_logged_in ? "Editor" : "Viewer" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/nano.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
    .pickr { margin-right: 10px; }
    .pcr-button { border: 1px solid #444 !important; border-radius: 4px !important; }
    body { background: #121212; color: #eee; font-family: 'Segoe UI', sans-serif; }
    .main-container { padding: 40px 20px; }
    .preview-pane { width: 400px; margin: 0 auto; border: 1px dashed #444; padding: 10px; background: #000; min-height: 500px; border-radius: 8px; }
    input.form-control, select.form-select { background: #222; color: #fff; border: 1px solid #444; }
    .theme-card { background: #1e1e1e; border: 1px solid #333; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.2s; }
    .theme-card:hover { border-color: #daa520; background: #252525; }
    .theme-card.active { border-color: #daa520; background: #2a2a2a; box-shadow: 0 0 10px rgba(218, 165, 20, 0.2); transform: translateX(5px); }
    .color-picker-group { display: flex; align-items: center; gap: 10px; }
    .sticky-column { position: sticky; top: 20px; height: calc(100vh - 60px); }
    .highlight-input { background-color: #3b310d !important; border-color: #daa520 !important; box-shadow: 0 0 10px rgba(218, 165, 20, 0.5); transform: scale(1.02); }
    [data-edit-key], [data-edit-keys] { cursor: help; }
    .preview-toggle-bar { background: #212529; border: 1px solid #343a40; border-radius: 8px; padding: 5px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; max-width: 500px; margin: 0 auto 10px auto; transition: background 0.2s; }
    .preview-toggle-bar:hover { background: #2c3034; }
    .text-muted {
        --bs-text-opacity: 1 !important;
        color: rgba(210, 210, 210, 0.75) !important;
    }
</style>
</head>
<body>

<div class="container-fluid main-container">
    <div class="row">
        <div class="col-md-5">
            <?php if ($is_logged_in): ?>
                <h3>🎨 Theme Editor (Admin)</h3>
                <div class="mb-4">
                    <label>Theme bearbeiten:</label>
                    <div class="d-flex gap-2">
                        <select id="adminThemeSelect" class="form-select" onchange="selectTheme(this.value, null)">
                            <?php foreach ($themes as $name => $cfg): ?>
                                <option value="<?= htmlspecialchars(
                                    $name,
                                ) ?>" <?= $name == $currentThemeName
    ? "selected"
    : "" ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <!-- NEU: Kopier-Button für Admins hinter der Selectbox -->
                        <button type="button" class="btn btn-outline-warning" id="copyThemeNameAdminBtn" onclick="copyThemeNameDirectly()" title="Theme-Namen kopieren">📋</button>
                        <button type="button" class="btn btn-outline-primary" onclick="createNew()">Neu</button>
                    </div>
                </div>

                <form id="themeForm" method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" id="themeNameInput" name="themeName" value="<?= htmlspecialchars(
                        $currentThemeName,
                    ) ?>">
                    <div class="row">
                        <div class="col-6">
                            <h5>Farben</h5>
                            <?php foreach ($colorFields as $field): ?>
                                <label><?= $field ?>:</label>
                                <div class="color-picker-group mb-2">
                                    <div id="picker-<?= $field ?>"></div>
                                    <input type="text" name="config[<?= $field ?>]" id="<?= $field ?>" class="form-control form-control-sm color-input" value="<?= htmlspecialchars(
    $currentConfig[$field] ?? "rgba(0,0,0,1)",
) ?>" oninput="updatePreview()">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <h5>Texte & Icons</h5>
                            <?php foreach ($textFields as $field): ?>
                                <label><?= $field ?>:</label>
                                <input type="text" name="config[<?= $field ?>]" id="<?= $field ?>" class="form-control form-control-sm" value="<?= htmlspecialchars(
    $currentConfig[$field] ?? "",
) ?>" oninput="updatePreview()">
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mt-4 mb-5">💾 Änderungen speichern</button>
                </form>

            <?php else: ?>
                <h3>✨ Theme Gallery</h3>
                <div class="mt-2 p-2 border border-warning rounded bg-dark d-flex align-items-center justify-content-between gap-3 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-warning" style="font-size: 1.2rem;">⚡</span>
                        <div style="line-height: 1.1;">
                            <strong class="text-warning" style="font-size: 0.9rem;">Foundry Macro</strong><br>
                            <small class="text-muted" style="font-size: 0.75rem;">Script-Inhalt kopieren</small>
                        </div>
                    </div>
                    <button class="btn btn-warning btn-sm fw-bold" id="copyMacroBtn" onclick="copyMacroToClipboard()">📋 Script kopieren</button>
                </div>

                <div class="mt-4 p-2 border border-info rounded bg-dark d-flex align-items-center justify-content-between gap-3 shadow-sm" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#installGuide" aria-expanded="true" aria-controls="installGuide">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-info" style="font-size: 1.2rem;">🛠️</span>
                                            <div style="line-height: 1.1;">
                                                <strong class="text-info" style="font-size: 0.9rem;">Infos &amp; Installation</strong><br>
                                                <small class="text-muted" style="font-size: 0.75rem;">Klicken zum Ausklappen</small>
                                            </div>
                                        </div>
                                        <span class="text-info me-2" id="collapseIcon">▼</span>
                                    </div>

                                    <div class="collapse" id="installGuide" style="">
                                        <div class="p-3 bg-dark border border-info border-top-0 rounded-bottom" style="font-size: 0.85rem; background-color: #1d1d1d !important;">
                                            <div class="mb-3">
                                                <span class="text-info mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">📖 Regeln &amp; Details:</span><br>
                                                <code class="text-info" style="word-break: break-all;">
                                                    <a href="shine-snatch_rules.php" target="_blank" style="text-decoration: none;">https://www.9ps.eu/shine-snatch/shine-snatch_rules.php</a>
                                                </code>
                                            </div>

                                            <h6 class="text-info mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">🛠️ Kurzanleitung zur Installation:</h6>
                                            <ol class="ps-3 mb-0 text-muted" style="line-height: 1.4;">
                                                <li class="mb-1">Wähle dein gewünschtes Theme und klicke danach oben auf <strong>"Script kopieren"</strong></li>
                                                <li class="mb-1">In Foundry klicke unten in der Schnellauswahlleiste mit einem <strong>Linksklick</strong> auf ein freies Feld (oder Rechtsklick zum Bearbeiten eines vorhandenen).</li>
                                                <li class="mb-1">Einen passenden <strong>Namen</strong> (z.B. Shine-Snatch) vergeben.</li>
                                                <li class="mb-1">Den <strong>Type</strong> von "Chat" auf <strong>"Script"</strong> ändern.</li>
                                                <li class="mb-1">Den Inhalt der Zwischenablage <strong>(Script kopieren)</strong> in das große Textfeld einfügen.</li>
                                                <li class="mb-1">Überprüfe dein gewünschtes Theme, es gibt 2 Sonder Themes die hier nicht angezeigt werden <strong>"Zufall"</strong> und <strong>"Kombo-Theme,Barde"</strong> (wählt automatisch ein Theme wenn eine Kombination gefunden worden ist, ansonsnten das Theme dahinter gewählt [z.B. Barde, Gold, Schmiede]).</li>
                                                <li class="mb-1">Items mit dem Namen <strong>"Shine-Snatch *"</strong> (* = die Nummer z.B. 1, 15, 53) werden automatisch aus dem Inventar, auch Container, des ausgewählen Charakter gesucht. Es geht auch <strong>"Shine-Snatch 23 - Name der Karte"</strong>.</li>
                                                <li><strong>Optionales Icon:</strong> Oben auf das Bild-Icon klicken und unten bei <em>Selected</em> diese URL eintragen:<br>
                                                    <code class="text-info" style="word-break: break-all;"><a href="https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp">https://www.9ps.eu/dnd/items/Krark/shine-snatch.webp</a></code>
                                                </li>
                                                <li class="mb-1">Dann noch speichern und <strong>viel Glück beim Spielen</strong></li>
                                            </ol>
                                        </div>
                                    </div>

                                    <p class="text-muted mt-3">Wähle ein Theme aus oder kopiere den Namen:</p>

                <div id="themeSelector" class="mt-4">
                    <?php foreach ($themes as $name => $cfg): ?>
                    <div class="theme-card <?= $name === $currentThemeName
                        ? "active"
                        : "" ?>" id="card_<?= htmlspecialchars(
    $name,
) ?>" onclick="selectTheme('<?= htmlspecialchars($name) ?>', this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span style="font-size: 1.2em; margin-right: 8px;"><?= htmlspecialchars(
                                        $cfg["headerIcon"] ?? "",
                                    ) ?></span>
                                    <strong style="color: <?= $cfg[
                                        "colorPrimary"
                                    ] ?? "#fff" ?>;"><?= htmlspecialchars(
    $name,
) ?></strong>
                                </div>

                                <div class="d-flex gap-1 align-items-center">
                                    <!-- NEU: Kopier-Button für reguläre User direkt vor dem Vorschau-Badge -->
                                    <button class="btn btn-sm btn-dark border-secondary p-1 py-0" style="font-size: 0.75rem;" onclick="event.stopPropagation(); copyThemeNameDirectly('<?= htmlspecialchars(
                                        $name,
                                    ) ?>', this)" title="Name kopieren">📋</button>
                                    <span class="badge border" style="background: <?= htmlspecialchars(
                                        $cfg["colorBg"] ?? "#121212",
                                    ) ?>; color: <?= htmlspecialchars(
    $cfg["colorPrimary"] ?? "#fff",
) ?>; border-color: <?= htmlspecialchars(
    $cfg["colorAccent"] ?? "#daa520",
) ?> !important;">Vorschau</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-7">
            <div class="sticky-column">
                <h3 class="text-center mb-4">👁️ Foundry Chat Vorschau</h3>

                <div class="preview-toggle-bar shadow-sm" onclick="togglePreviewBox()" id="toggleBar">
                    <span class="small fw-bold text-muted text-uppercase">⚙️ Vorschau-Einstellungen</span>
                    <span class="text-muted" id="toggleIcon">▼</span>
                </div>
                <div id="previewSettingsBox" class="card bg-dark border-secondary mb-4 p-3 shadow-lg" style="max-width: 500px; margin: 0 auto; display: none;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="small text-muted mb-1 d-block">Gezogene Karten (IDs):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-secondary border-secondary text-white">🃏</span>
                                <input type="text" id="testHand" class="form-control" value="28,22,5,20,37" onchange="loadFreshContentFromServer()">
                                <button class="btn btn-outline-warning" type="button" onclick="rollRandomHand('testHand')">🎲</button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted mb-1 d-block">Sammelkarten / Owned Cards (IDs):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-secondary border-secondary text-white">🌟</span>
                                <input type="text" id="ownedCards" class="form-control" value="5,12,27" onchange="loadFreshContentFromServer()">
                                <button class="btn btn-outline-warning" type="button" onclick="rollRandomHand('ownedCards')">🎲</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center">
                    <div id="previewArea" class="preview-pane d-flex flex-column justify-content-start">
                        <span class="text-muted text-center m-5">Lade Vorschau...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const allThemesData = <?= json_encode($themes) ?>;
let currentSelectedThemeName = "<?= htmlspecialchars($currentThemeName) ?>";
const isAdmin = document.getElementById('themeForm') !== null;

document.addEventListener('DOMContentLoaded', () => {
    if (isAdmin) {
        const colorFields = ['colorPrimary', 'colorGlowMain', 'colorBoltCore', 'colorAccent', 'colorBg', 'colorBgCard', 'shadowColor', 'colorSpecialBg', 'colorTextMain', 'colorTextMuted', 'colorTextComboIds'];
        colorFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input) return;
            const pickr = Pickr.create({
                el: `#picker-${field}`,
                theme: 'nano',
                default: input.value || '#000000',
                components: { preview: true, opacity: true, hue: true, interaction: { input: true, save: true } },
                strings: { save: 'OK' }
            });
            input.dataset.pickrInstance = 'pickr_' + field;
            window['pickr_' + field] = pickr;

            pickr.on('change', (color) => {
                input.value = color.toRGBA().toString(0);
                updatePreview();
            });
            input.addEventListener('change', () => { pickr.setColor(input.value); });
        });

        document.getElementById('themeForm').addEventListener('input', updatePreview);
    }
    loadFreshContentFromServer();
});

function togglePreviewBox() {
    const box = document.getElementById('previewSettingsBox');
    const icon = document.getElementById('toggleIcon');
    const isHidden = box.style.display === 'none' || box.style.display === '';
    box.style.display = isHidden ? 'block' : 'none';
    icon.innerText = isHidden ? '▼' : '◀';
}

function tagPreviewTextElements() {
    const previewArea = document.getElementById('previewArea');
    if (!previewArea) return;

    const activeConfig = allThemesData[currentSelectedThemeName];
    if (!activeConfig) return;

    const textFields = ["headerIcon", "headerTitle", "specialCardEmoji", "labelHand", "labelHandSum", "labelSpecialBonus", "labelSubTotal", "labelCombos", "iconCombo", "labelUnused", "iconUnused", "labelTotal"];

    const textNodes = [];
    const walk = document.createTreeWalker(previewArea, NodeFilter.SHOW_TEXT, null, false);
    let node;
    while (node = walk.nextNode()) {
        if (!node.parentElement.closest('[data-text-key]')) {
            textNodes.push(node);
        }
    }

    const sortedFields = [...textFields].sort((a, b) => {
        const valA = (isAdmin ? document.getElementById(a)?.value : activeConfig[a]) || "";
        const valB = (isAdmin ? document.getElementById(b)?.value : activeConfig[b]) || "";
        return valB.length - valA.length;
    });

    textNodes.forEach(node => {
        let text = node.nodeValue;
        let parent = node.parentElement;
        if (!parent) return;

        for (const fieldId of sortedFields) {
            const dbValue = (isAdmin ? document.getElementById(fieldId)?.value : activeConfig[fieldId]);
            if (dbValue && dbValue.trim() !== "" && text.includes(dbValue)) {
                const idx = text.indexOf(dbValue);
                const before = text.substring(0, idx);
                const after = text.substring(idx + dbValue.length);

                const newSpan = document.createElement('span');
                newSpan.setAttribute('data-text-key', fieldId);
                newSpan.innerText = dbValue;

                const frag = document.createDocumentFragment();
                if (before) frag.appendChild(document.createTextNode(before));
                frag.appendChild(newSpan);
                if (after) frag.appendChild(document.createTextNode(after));

                parent.replaceChild(frag, node);
                break;
            }
        }
    });
}

// =========================================================================
// OPTIMIERTES LIVE-RENDERING (Verhindert Cross-Over-Überschreibungen auf der Score-Box)
// =========================================================================
function updatePreview() {
    const previewArea = document.getElementById('previewArea');
    if (!previewArea) return;

    const activeConfig = allThemesData[currentSelectedThemeName] || {};

    const colorFields = ["colorPrimary", "colorGlowMain", "colorBoltCore", "colorAccent", "colorBg", "colorBgCard", "shadowColor", "colorTextMain", "colorSpecialBg", "colorTextMuted", "colorTextComboIds"];
    colorFields.forEach(fieldId => {
        let newValue = isAdmin ? document.getElementById(fieldId)?.value : activeConfig[fieldId];
        if (!newValue) return;

        let targets = Array.from(previewArea.querySelectorAll(`[data-edit-keys*="${fieldId}"], [data-edit-key*="${fieldId}"]`));

        // Injektion für colorGlowMain, da der Key in der API-Rückgabe der Score-Box fehlt
        if (fieldId === 'colorGlowMain') {
            const h2El = previewArea.querySelector('h2');
            if (h2El && !targets.includes(h2El)) targets.push(h2El);

            // Finde die finale Punktebox über ihr inline-CSS (Präzise Erkennung!)
            const totalScoreEl = Array.from(previewArea.querySelectorAll('div')).find(div =>
                div.style.fontSize === '1.4rem' || div.style.textAlign === 'center' || div.getAttribute('data-edit-keys')?.includes('colorBoltCore')
            );
            if (totalScoreEl && !targets.includes(totalScoreEl)) targets.push(totalScoreEl);
        }

        targets.forEach(el => {
            // Ist dieses Element die finale Gesamt-Punkte-Anzeige?
            const isTotalScore = el.style.fontSize === '1.4rem' ||
                                 el.style.textAlign === 'center' ||
                                 el.querySelector('[data-text-key="labelTotal"]') ||
                                 el.textContent.includes('SHOW-SCORE');

            if (fieldId === 'colorBg') el.style.backgroundColor = newValue;
            else if (fieldId === 'colorBgCard') el.style.backgroundColor = newValue;
            else if (fieldId === 'colorTextMain' || fieldId === 'colorTextMuted' || fieldId === 'colorTextComboIds') el.style.color = newValue;
            else if (fieldId === 'colorPrimary') {
                // Schutz: Gesamt-Score nutzt primary nur für den Außenrahmen, nicht für die Textfarbe!
                if (!isTotalScore && (el.tagName === 'SPAN' || el.tagName === 'DIV')) el.style.color = newValue;
                el.style.borderBottomColor = newValue;
                el.style.borderColor = newValue;
            }
            else if (fieldId === 'colorAccent') {
                // Schutz: Gesamt-Score Textfarbe wird nicht von colorAccent überschrieben
                if (!isTotalScore) el.style.color = newValue;
            }
            else if (fieldId === 'shadowColor') el.style.boxShadow = `0 6px 12px ${newValue}`;
            else if (fieldId === 'colorSpecialBg') el.style.backgroundColor = newValue;
            else if (fieldId === 'colorBoltCore' || fieldId === 'colorGlowMain') {
                const isHeader = el.tagName === 'H2' || el.closest('h2');

                if (isHeader || isTotalScore) {
                    const core = isAdmin ? (document.getElementById('colorBoltCore')?.value || '#fff') : (activeConfig['colorBoltCore'] || '#fff');
                    const glow = isAdmin ? (document.getElementById('colorGlowMain')?.value || 'rgba(0,0,0,0)') : (activeConfig['colorGlowMain'] || 'rgba(0,0,0,0)');
                    el.style.textShadow = `0 0 10px ${core}, 0 0 20px ${glow}`;
                    el.style.color = core; // Erzwinge die helle Kernfarbe für die Leucht-Texte
                } else {
                    // Alle normalen Zeilen im Content-Bereich bleiben sauber ohne Glow scharf gestellt
                    el.style.textShadow = 'none';
                    if (fieldId === 'colorBoltCore') el.style.color = newValue;
                }
            }
        });
    });

    const textFields = ["headerIcon", "headerTitle", "specialCardEmoji", "labelHand", "labelHandSum", "labelSpecialBonus", "labelSubTotal", "labelCombos", "iconCombo", "labelUnused", "iconUnused", "labelTotal"];
    textFields.forEach(fieldId => {
        let newValue = isAdmin ? document.getElementById(fieldId)?.value : activeConfig[fieldId];
        if (newValue === undefined || newValue === null) return;

        const targets = previewArea.querySelectorAll(`[data-text-key="${fieldId}"]`);
        targets.forEach(el => {
            el.innerText = newValue;
        });
    });
}

function loadFreshContentFromServer() {
    const testHandStr = document.getElementById('testHand')?.value || '';
    const ownedCardsStr = document.getElementById('ownedCards')?.value || '';

    const ownedCardsArray = ownedCardsStr ? ownedCardsStr.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id)) : [];
    const overrideHandArray = testHandStr ? testHandStr.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id)) : [];

    const currentVersion = "<?= htmlspecialchars($currentVersion) ?>";
    const apiUrlString = "<?= htmlspecialchars($apiUrl) ?>";

    const payload = {
        actorId: "discord-123456789",
        actorName: "TestUser",
        playerName: "DerSchreckenVomServer",
        serverId: "123456789012345678",
        theme: currentSelectedThemeName,
        world: "Test-Umgebung",
        version: currentVersion,
        scriptVersion: currentVersion,
        ownedCards: ownedCardsArray,
        overrideHand: overrideHandArray
    };

    const previewArea = document.getElementById('previewArea');
    fetch(apiUrlString, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) throw new Error('Netzwerk-Fehler');
        return response.json();
    })
    .then(data => {
        if (data && data.html) {
            previewArea.innerHTML = data.html;
            tagPreviewTextElements();
            updatePreview();
        }
    })
    .catch(err => {
        console.error("Fehler beim API-Inhalt:", err);
        previewArea.innerHTML = `<p class="text-danger p-3">Verbindungsfehler zur API (${apiUrlString})</p>`;
    });
}

function selectTheme(themeName, cardElement = null) {
    currentSelectedThemeName = themeName;

    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    if (cardElement) {
        cardElement.classList.add('active');
    } else {
        const el = document.getElementById('card_' + themeName);
        if (el) el.classList.add('active');
        const select = document.getElementById('adminThemeSelect');
        if (select) select.value = themeName;
    }

    if (isAdmin && allThemesData[themeName]) {
        document.getElementById('themeNameInput').value = themeName;
        const cfg = allThemesData[themeName];
        Object.keys(cfg).forEach(key => {
            const input = document.getElementById(key);
            if (input) {
                input.value = cfg[key];
                if (input.dataset.pickrInstance) {
                    window[input.dataset.pickrInstance].setColor(cfg[key]);
                }
            }
        });
    }

    loadFreshContentFromServer();
}

function rollRandomHand(inputId) {
    let rands = [];
    for (let i = 0; i < 5; i++) { rands.push(Math.floor(Math.random() * 59) + 1); }
    const input = document.getElementById(inputId);
    if (input) {
        input.value = rands.join(',');
        loadFreshContentFromServer();
    }
}

if (isAdmin) {
    document.getElementById('previewArea').addEventListener('mouseover', (e) => {
        const target = e.target.closest('[data-edit-keys], [data-edit-key]');
        if (!target) return;
        const attr = target.getAttribute('data-edit-keys') || target.getAttribute('data-edit-key');
        const keys = attr.split(',');
        document.querySelectorAll('.highlight-input').forEach(el => el.classList.remove('highlight-input'));
        keys.forEach(key => {
            const inputField = document.getElementById(key.trim());
            if (inputField) inputField.classList.add('highlight-input');
        });
    });
    document.getElementById('previewArea').addEventListener('mouseout', () => {
        document.querySelectorAll('.highlight-input').forEach(el => el.classList.remove('highlight-input'));
    });
}

function createNew() {
    const name = prompt("Name des neuen Themes:");
    if (name) {
        document.getElementById('themeNameInput').value = name;
        document.getElementById('themeForm').submit();
    }
}

async function copyMacroToClipboard() {
    const btn = document.getElementById('copyMacroBtn');
    const originalText = btn.innerHTML;
    try {
        const response = await fetch('foundry-macro.js?t=' + new Date().getTime(), { cache: 'no-store' });
        if (!response.ok) throw new Error('Datei nicht gefunden');
        let macroCode = await response.text();

        macroCode = macroCode.replace(/const activeTheme = ".*?";/, `const activeTheme = "${currentSelectedThemeName}";`);

        await navigator.clipboard.writeText(macroCode);
        btn.innerHTML = "✅ Script kopiert!";
        btn.classList.replace('btn-warning', 'btn-success');

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('btn-success', 'btn-warning');
        }, 2000);
    } catch (err) {
        alert('Konnte foundry-macro.js nicht laden.');
    }
}

// NEU: Universelle JavaScript-Funktion zum direkten Kopieren des Theme-Namens
async function copyThemeNameDirectly(themeName = null, btnElement = null) {
    // Wenn kein Name übergeben wurde, lade das aktuell im Editor ausgewählte Theme
    const targetName = themeName || currentSelectedThemeName;
    const targetBtn = btnElement || document.getElementById('copyThemeNameAdminBtn');
    const originalContent = targetBtn.innerHTML;

    try {
        await navigator.clipboard.writeText(targetName);
        targetBtn.innerHTML = "✅";

        setTimeout(() => {
            targetBtn.innerHTML = originalContent;
        }, 1500);
    } catch (err) {
        console.error("Fehler beim Kopieren des Theme-Namens:", err);
    }
}
</script>
</body>
</html>
