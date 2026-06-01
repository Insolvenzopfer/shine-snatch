<?php
session_start();

// Prüfen ob Admin (Session ID gesetzt)
$is_logged_in = isset($_SESSION["loggedin"]);

$config = require "config.php";
$currentVersion = $config["current_version"];

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
            "labelCombosSum" => "Bonus:",
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

// --- DATA FETCHING ---
$querySQL = "SELECT
    theme_name,
    color_primary AS colorPrimary,
    color_glow_main AS colorGlowMain,
    color_bolt_core AS colorBoltCore,
    color_accent AS colorAccent,
    color_bg AS colorBg,
    color_bg_card AS colorBgCard,
    shadow_color AS shadowColor,
    color_text_main AS colorTextMain,
    color_special_bg AS colorSpecialBg,
    color_text_muted AS colorTextMuted,
    color_text_combo_ids AS colorTextComboIds,
    header_icon AS headerIcon,
    header_title AS headerTitle,
    special_card_emoji AS specialCardEmoji,
    label_hand AS labelHand,
    label_hand_sum AS labelHandSum,
    label_special_bonus AS labelSpecialBonus,
    label_sub_total AS labelSubTotal,
    label_combos AS labelCombos,
    icon_combo AS iconCombo,
    label_unused AS labelUnused,
    icon_unused AS iconUnused,
    label_total AS labelTotal
FROM snatch_themes ORDER BY theme_name ASC";

$themes = [];
$dbThemes = $pdo->query($querySQL)->fetchAll(PDO::FETCH_ASSOC);

foreach ($dbThemes as $row) {
    $name = $row["theme_name"];
    unset($row["theme_name"]);
    $themes[$name] = $row;
}

$currentThemeName = $_GET["theme"] ?? (array_key_first($themes) ?? "default");
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
    .preview-pane {
        width: 400px;
        margin: 0 auto;
        border: 1px dashed #444;
        padding: 10px;
        background: #000;
        min-height: 500px;
        border-radius: 8px;
    }
    input.form-control, select.form-select { background: #222; color: #fff; border: 1px solid #444; }
    .theme-card {
        background: #1e1e1e; border: 1px solid #333; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.2s;
    }
    .theme-card:hover { border-color: #daa520; background: #252525; }
    .theme-card.active {
        border-color: #daa520;
        background: #2a2a2a;
        box-shadow: 0 0 10px rgba(218, 165, 20, 0.2);
        transition: all 0.3s ease;
        transform: translateX(5px);
    }
    .color-picker-group { display: flex; align-items: center; gap: 10px; }
    input[type="color"] { width: 40px; height: 30px; border: none; background: none; cursor: pointer; }
    h3 { border-bottom: 1px solid #7c7c7c; padding-bottom: 10px; margin-bottom: 20px; }
    .hidden-radio { display: none; }
    .text-muted { color: #bdbdbd !important; }
    .sticky-column {
        position: sticky;
        top: 20px;
        height: calc(100vh - 60px);
    }
    .highlight-input {
        background-color: #3b310d !important;
        border-color: #daa520 !important;
        box-shadow: 0 0 10px rgba(218, 165, 20, 0.5);
        transition: all 0.2s ease;
        transform: scale(1.02);
    }
    [data-edit-key], [data-edit-keys] { cursor: help; }
    .preview-toggle-bar {
        background: #212529;
        border: 1px solid #343a40;
        border-radius: 8px;
        padding: 5px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 500px;
        margin: 0 auto 10px auto;
        transition: background 0.2s;
    }
    .preview-toggle-bar:hover { background: #2c3034; }
    .preview-toggle-bar i { transition: transform 0.3s; }
    .collapsed i { transform: rotate(-90deg); }
</style>
</head>
<body>

<div class="container-fluid main-container">
    <div class="row">
        <div class="col-md-5">
            <?php if ($is_logged_in): ?>
                <h3>🎨 Theme Editor (Admin)</h3>
                <form method="GET" class="mb-4">
                    <label>Theme bearbeiten:</label>
                    <div class="d-flex gap-2">
                        <select name="theme" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($themes as $name => $cfg): ?>
                                <option value="<?= htmlspecialchars(
                                    $name,
                                ) ?>" <?= $name == $currentThemeName
    ? "selected"
    : "" ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary" onclick="createNew()">Neu</button>
                    </div>
                </form>

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
                                        <input type="text" name="config[<?= $field ?>]" id="<?= $field ?>"
                                            class="form-control form-control-sm color-input"
                                            value="<?= htmlspecialchars(
                                                $currentConfig[$field] ??
                                                    "rgba(0,0,0,1)",
                                            ) ?>"
                                            oninput="updatePreview()">
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
                    <button type="submit" class="btn btn-success w-100 mt-4 mb-5">Speichern</button>
                </form>

            <?php else: ?>
                <h3>✨ Theme Gallery</h3>

                    <!-- ⚡ FOUNDRY MACRO SKRIPT KOPIEREN BUTTON -->
                    <div class="mt-2 p-2 border border-warning rounded bg-dark d-flex align-items-center justify-content-between gap-3 shadow-sm">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-warning" style="font-size: 1.2rem;">⚡</span>
                            <div style="line-height: 1.1;">
                                <strong class="text-warning" style="font-size: 0.9rem;">Foundry Macro</strong><br>
                                <small class="text-muted" style="font-size: 0.75rem;">Script-Inhalt kopieren</small>
                            </div>
                        </div>
                        <button class="btn btn-warning btn-sm" id="copyMacroBtn" onclick="copyMacroToClipboard()" style="white-space: nowrap; min-width: 160px; font-weight: bold;">
                            📋 Script kopieren
                        </button>
                    </div>

                    <!-- 🛠️ INFOS & INSTALLATION (COLLAPSE BOX) -->
                    <div class="mt-4 p-2 border border-info rounded bg-dark d-flex align-items-center justify-content-between gap-3 shadow-sm"
                         style="cursor: pointer;"
                         data-bs-toggle="collapse"
                         data-bs-target="#installGuide"
                         aria-expanded="false"
                         aria-controls="installGuide">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-info" style="font-size: 1.2rem;">🛠️</span>
                            <div style="line-height: 1.1;">
                                <strong class="text-info" style="font-size: 0.9rem;">Infos & Installation</strong><br>
                                <small class="text-muted" style="font-size: 0.75rem;">Klicken zum Ausklappen</small>
                            </div>
                        </div>
                        <span class="text-info me-2" id="collapseIcon">▼</span>
                    </div>

                    <div class="collapse" id="installGuide">
                        <div class="p-3 bg-dark border border-info border-top-0 rounded-bottom" style="font-size: 0.85rem; background-color: #1d1d1d !important;">
                            <div class="mb-3">
                                <span class="text-info mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">📖 Regeln & Details:</span><br>
                                <code class="text-info" style="word-break: break-all;">
                                    <a href="shine-snatch_rules.php" target="_blank" style="text-decoration: none;">https://<?php echo $_SERVER[
                                        "HTTP_HOST"
                                    ]; ?>/shine-snatch/shine-snatch_rules.php</a>
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
                                    <code class="text-info" style="word-break: break-all;"><a href="https://<?php echo $_SERVER[
                                        "HTTP_HOST"
                                    ]; ?>/dnd/items/Krark/shine-snatch.webp">https://<?php echo $_SERVER[
    "HTTP_HOST"
]; ?>/dnd/items/Krark/shine-snatch.webp</a></code>
                                </li>
                                <li class="mb-1">Dann noch speichern und <strong>viel Glück beim Spielen</strong></li>
                            </ol>
                        </div>
                    </div>

                    <p class="text-muted mt-3">Wähle ein Theme aus oder kopiere den Namen:</p>


                <div id="themeSelector">
                    <?php foreach ($themes as $name => $cfg): ?>
                        <div class="theme-card w-100 <?= $name ==
                        $currentThemeName
                            ? "active"
                            : "" ?>" onclick="document.getElementById('radio_<?= md5(
    $name,
) ?>').click()">
                            <input type="radio" id="radio_<?= md5(
                                $name,
                            ) ?>" name="viewerTheme" class="hidden-radio" value="<?= htmlspecialchars(
    json_encode($cfg),
) ?>" onchange="selectViewerTheme(this, '<?= htmlspecialchars($name) ?>')">
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
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge" style="background: <?= $cfg[
                                        "colorBg"
                                    ] ?? "#333" ?>; color: <?= $cfg[
    "colorBoltCore"
] ?? "#fff" ?>; border: 1px solid rgba(255,255,255,0.2);">Vorschau</span>
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

                <!-- Preview Settings Box -->
                <div class="preview-toggle-bar shadow-sm" onclick="togglePreviewBox()" id="toggleBar">
                    <span class="small fw-bold text-muted text-uppercase">⚙️ Vorschau-Einstellungen</span>
                    <span class="text-muted" id="toggleIcon">▼</span>
                </div>
                <div id="previewSettingsBox" class="card bg-dark border-secondary mb-4 p-3 shadow-lg" style="max-width: 500px; margin: 0 auto; display: none;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="small text-muted mb-1 d-block">Gezogene Karten (IDs 1-60):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-secondary border-secondary text-white" style="width: 40px; justify-content: center;">🃏</span>
                                <input type="text" id="testHand" class="form-control" value="20,57,16,37,27" oninput="updatePreview()">
                                <button class="btn btn-outline-warning" type="button" onclick="rollRandomHand('testHand')">🎲</button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted mb-1 d-block">Sammelkarten / Owned Cards (IDs 1-60):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-secondary border-secondary text-white" style="width: 40px; justify-content: center;">🌟</span>
                                <input type="text" id="ownedCards" class="form-control" value="20,16,27" oninput="updatePreview()">
                                <button class="btn btn-outline-warning" type="button" onclick="rollRandomHand('ownedCards')">🎲</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center">
                    <div id="previewArea" class="preview-pane"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePreviewBox() {
    const box = document.getElementById('previewSettingsBox');
    const icon = document.getElementById('toggleIcon'); // Element für den Pfeil
    const bar = document.getElementById('toggleBar');   // Element für die Leiste

    // Prüfen, ob die Box aktuell ausgeblendet ist
    const isHidden = box.style.display === 'none' || box.style.display === '';

    if (isHidden) {
        box.style.display = 'block';
        icon.innerText = '▼';
        bar.classList.remove('collapsed');
    } else {
        box.style.display = 'none';
        icon.innerText = '◀';
        bar.classList.add('collapsed');
    }
}

// Generiert zufällige Karten-IDs zwischen 1 und 60
function rollRandomHand(inputId) {
    const count = inputId === 'testHand' ? 5 : 3; // 5 Karten für Hand, 3 für Sammelkarten
    let rands = [];
    while(rands.length < count){
        let r = Math.floor(Math.random() * 60) + 1;
        if(rands.indexOf(r) === -1) rands.push(r);
    }
    document.getElementById(inputId).value = rands.join(',');
    updatePreview();
}

let currentViewerConfig = <?= json_encode($currentConfig) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const colorFields = ['colorPrimary', 'colorGlowMain', 'colorBoltCore', 'colorAccent', 'colorBg', 'colorBgCard', 'shadowColor', 'colorSpecialBg', 'colorTextMain', 'colorTextMuted', 'colorTextComboIds'];
    colorFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input) return;
        const pickr = Pickr.create({
            el: `#picker-${field}`,
            theme: 'nano',
            default: input.value || '#000000',
            components: {
                preview: true, opacity: true, hue: true,
                interaction: { input: true, save: true }
            },
            strings: { save: 'OK' }
        });
        pickr.on('change', (color) => {
            input.value = color.toRGBA().toString(0);
            updatePreview();
        });
        input.addEventListener('change', () => { pickr.setColor(input.value); });
    });
    updatePreview();
});

function selectViewerTheme(radio, name) {
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    radio.parentElement.classList.add('active');
    currentViewerConfig = JSON.parse(radio.value);
    updatePreview();
}

async function updatePreview() {
    const previewArea = document.getElementById('previewArea');
    if (!previewArea) return;
    previewArea.style.opacity = "0.5";

    let themeConfig = {};
    let previewTimeout = null;
    let lastRequestTime = 0;
    const MIN_REQUEST_INTERVAL = 250; // Maximal 4 Anfragen pro Sekunde (1000ms / 250ms)

    if (document.getElementById('themeForm')) {
        document.getElementById('themeForm').addEventListener('input', () => {
            // 1. Alten Timer lￃﾶschen, falls der Benutzer noch zieht/tippt
            if (previewTimeout) {
                clearTimeout(previewTimeout);
            }

            // 2. Neuen Timer setzen (wartet, bis 250ms keine Bewegung mehr war)
            previewTimeout = setTimeout(() => {
                const currentTime = performance.now();

                // Zusￃﾤtzliche Absicherung: Erzwinge Mindestabstand zwischen den echten API-Senden
                if (currentTime - lastRequestTime >= MIN_REQUEST_INTERVAL) {
                    lastRequestTime = currentTime;
                    updatePreview();
                } else {
                    // Falls zu schnell, schiebe es noch einmal ein kurzes Stￃﾼck nach hinten
                    setTimeout(updatePreview, MIN_REQUEST_INTERVAL);
                }
            }, 250);
        });
    }

    if (document.getElementById('themeForm')) {
        const inputs = document.getElementById('themeForm').querySelectorAll('input[name^="config["]');
        inputs.forEach(input => {
            const start = input.name.indexOf('[') + 1;
            const end = input.name.indexOf(']');
            const key = input.name.substring(start, end);
            themeConfig[key] = input.value;
        });
    } else {
        themeConfig = currentViewerConfig;
    }

    const handIds = document.getElementById('testHand').value.split(',').map(Number);
    const ownedIds = document.getElementById('ownedCards').value.split(',').map(Number);

    let rawText = "";

    try {
        const response = await fetch('<?php echo $config["api_url"]; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                theme: "PREVIEW_MODE",
                customConfig: themeConfig,
                overrideHand: handIds,
                ownedCards: ownedIds,
                world: "Theme-Editor",
                version: "" ,
                url: window.location.origin,
                scriptVersion: <?php echo $currentVersion; ?>,
            })
        });

        rawText = await response.text(); // Sichere die rohe Antwort ab

        if (!response.ok) {
            previewArea.innerHTML = `<p class='text-danger p-3'>Server-Fehler (${response.status}). Details in der Webkonsole (F12)!</p>`;
            console.error(`[API-Error] HTTP Status ${response.status}. Rohe Server-Antwort:`, rawText);
            return;
        }

        // Versuche das JSON manuell zu parsen
        const result = JSON.parse(rawText);

        if (result.html) {
            previewArea.innerHTML = result.html;
        } else if (result.text) {
            previewArea.innerHTML = result.text;
        } else if (result.error) {
            previewArea.innerHTML = `<p class='text-danger p-3'>API Fehler: ${result.error}</p>`;
            console.warn("[API-Warning] Fehler vom Backend zurￃﾼckgegeben:", result.error);
        } else {
            previewArea.innerHTML = `<p class='text-warning p-3'>Unerwartetes Antwortformat.</p>`;
            console.log("Unerwartete API-Antwort (valides JSON):", result);
        }

    } catch (e) {
        // Falls das JSON-Parsing fehlschlￃﾤgt, jagen wir die rohe Antwort in die Konsole
        previewArea.innerHTML = `<p class='text-danger p-3'>￢ﾚﾠ￯ﾸﾏ JSON-Parsing Fehler. Die fehlerhafte Server-Ausgabe wurde in die Webkonsole (F12) gedruckt!</p>`;

        console.group("￰ﾟﾔﾴ Snatch-Vorschau: JSON-Parsing fehlgeschlagen");
        console.error("JavaScript-Fehler:", e.message);
        console.warn("Der Server hat keinen sauberen JSON-String geliefert. Das passiert meistens durch 'echos', 'print_r' oder PHP-Warnungen im Backend.");
        console.log("--- ROHE SERVER-ANTWORT (START) ---");
        console.log(rawText || "(Keine Antwort vom Server erhalten)");
        console.log("--- ROHE SERVER-ANTWORT (ENDE) ---");
        console.groupEnd();
    } finally {
        previewArea.style.opacity = "1";
    }
}

function createNew() {
    const name = prompt("Name des neuen Themes:");
    if (name) {
        document.getElementById('themeNameInput').value = name;
        document.getElementById('themeForm').submit();
    }
}

// REPARIERTER HOVER-EFFEKT (Unterstￃﾼtzt data-edit-key UND data-edit-keys)
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

// Funktion zum Kopieren des Makro-Codes
async function copyMacroToClipboard() {
    const btn = document.getElementById('copyMacroBtn');
    const originalText = btn.innerHTML;

    try {
        // 1. Script-Datei vom Server laden
        const response = await fetch('foundry-macro.js?t=' + new Date().getTime(), {
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Datei nicht gefunden');
        let macroCode = await response.text();

        // 2. Das aktuell ausgewählte Theme im Script ersetzen
        // Sucht im Script nach: const activeTheme = "..."; und ersetzt es
        const themeName = document.querySelector('.theme-card.active strong')?.innerText || "Gold";
        macroCode = macroCode.replace(/const activeTheme = ".*?";/, `const activeTheme = "${themeName}";`);

        // 3. In die Zwischenablage kopieren
        await navigator.clipboard.writeText(macroCode);

        // 4. Feedback für den User
        btn.innerHTML = "✅ Script kopiert!";
        btn.classList.replace('btn-warning', 'btn-success');

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('btn-success', 'btn-warning');
        }, 2000);

    } catch (err) {
        console.error('Fehler beim Kopieren:', err);
        alert('Konnte foundry-macro.js nicht laden. Sag bitte dem Admin bescheid.');
    }
}

// Navigation mit Pfeiltasten
document.addEventListener('keydown', function(e) {
    // 1. Verhindern, dass navigiert wird, wenn in ein Input/Select getippt wird
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
        return;
    }

    // 2. Navigation für die Theme-Liste (Viewer-Modus)
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); // Verhindert das Scrollen der Seite bei Tastendruck

        const cards = Array.from(document.querySelectorAll('.theme-card'));
        const activeCard = document.querySelector('.theme-card.active');
        let currentIndex = cards.indexOf(activeCard);

        if (e.key === 'ArrowDown') {
            if (currentIndex < cards.length - 1) {
                cards[currentIndex + 1].click();
                cards[currentIndex + 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            if (currentIndex > 0) {
                cards[currentIndex - 1].click();
                cards[currentIndex - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }
});

// Sicherer Check für das Auf-/Zuklappen des Hilfe-Icons
const collapseElement = document.getElementById('installGuide');
const collapseIcon = document.getElementById('collapseIcon');

if (collapseElement && collapseIcon) {
    collapseElement.addEventListener('show.bs.collapse', () => {
        collapseIcon.innerText = '▲';
    });
    collapseElement.addEventListener('hide.bs.collapse', () => {
        collapseIcon.innerText = '▼';
    });
}


</script>
</body>
</html>
