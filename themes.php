<?php
session_start();
$jsonFile = 'themes.json';

// Prüfen ob Admin (Session ID gesetzt)
// Ersetze 'admin_logged_in' durch deine tatsächliche Session-Variable
$is_logged_in = isset($_SESSION['user_id']);


// Falls die Datei nicht existiert oder leer ist, erstelle sie mit Start-Inhalt
if (!file_exists($jsonFile) || filesize($jsonFile) == 0) {
    // ... (Dein bisheriger initialThemes Block hier)
    $initialThemes = [
        "Gold-Standard" => ["colorPrimary" => "#daa520", "colorBoltCore" => "#ffffff", "colorGlowMain" => "rgba(218, 165, 20, 0.4)", "colorAccent" => "#daa520", "colorBg" => "#1a1c1a", "colorBgCard" => "rgba(255, 255, 255, 0.05)", "shadowColor" => "rgba(0, 0, 0, 0.6)", "colorTextMain" => "#eeeeee", "colorTextMuted" => "#666666", "colorTextComboIds" => "#aaaaaa", "headerIcon" => "🎴", "headerTitle" => "Shine-Snatch", "labelHand" => "Die Hand des Schicksals:", "labelHandSum" => "Basis-Wert:", "labelSubTotal" => "Zwischensumme:", "labelCombos" => "Aktive Synergien:", "labelCombosSum" => "Bonus:", "iconCombo" => "✦", "labelUnused" => "Verfallene Pfade:", "iconUnused" => "✧", "labelTotal" => "TOTAL:", "specialCardEmoji" => "🌟", "labelSpecialBonus" => "Sammel-Bonus:"],
        "Barde" => ["colorPrimary" => "#fb7185", "colorBoltCore" => "#ffffff", "colorGlowMain" => "rgba(251, 113, 133, 0.5)", "colorAccent" => "#f472b6", "colorBg" => "#1e1b4b", "colorBgCard" => "rgba(255, 255, 255, 0.05)", "shadowColor" => "rgba(244, 114, 182, 0.3)", "colorTextMain" => "#fae8ff", "colorTextMuted" => "#581c87", "colorTextComboIds" => "#f5d0fe", "headerIcon" => "🎭", "headerTitle" => "Barden-Snatch", "labelHand" => "Deine aktuelle Setlist:", "labelHandSum" => "Grund-Rhythmus:", "labelSubTotal" => "Melodische Basis:", "labelCombos" => "Harmonische Akkorde:", "labelCombosSum" => "Applaus-Bonus:", "iconCombo" => "🎶", "labelUnused" => "Verstummte Noten:", "iconUnused" => "🔇", "labelTotal" => "SHOW-SCORE:", "specialCardEmoji" => "✨", "labelSpecialBonus" => "Zugabe-Bonus:"],
        "Pirat" => ["colorPrimary" => "#2dd4bf", "colorBoltCore" => "#ffffff", "colorGlowMain" => "rgba(45, 212, 191, 0.4)", "colorAccent" => "#f59e0b", "colorBg" => "#0f172a", "colorBgCard" => "rgba(15, 23, 42, 0.7)", "shadowColor" => "rgba(245, 158, 11, 0.2)", "colorTextMain" => "#e2e8f0", "colorTextMuted" => "#475569", "colorTextComboIds" => "#fbbf24", "headerIcon" => "🏴‍☠️", "headerTitle" => "Plünder-Snatch", "labelHand" => "Dein Anteil der Beute:", "labelHandSum" => "Goldwert:", "labelSubTotal" => "Gesicherte Beute:", "labelCombos" => "Seemannsgarn:", "labelCombosSum" => "Piraten-Bonus:", "iconCombo" => "⚓", "labelUnused" => "Über Bord:", "iconUnused" => "🌊", "labelTotal" => "SCHATZWERT:", "specialCardEmoji" => "🦜", "labelSpecialBonus" => "Kapitäns-Bonus:"]
    ];
    file_put_contents($jsonFile, json_encode($initialThemes, JSON_PRETTY_PRINT));
}

$themes = json_decode(file_get_contents($jsonFile), true) ?: [];

$colorFields = [
    'colorPrimary', 'colorGlowMain', 'colorBoltCore', 'colorAccent', 
    'colorBg', 'colorBgCard', 'shadowColor', 'colorTextMain', 'colorSpecialBg',
    'colorTextMuted', 'colorTextComboIds'
];

$textFields = [
    'headerIcon', 'headerTitle', 'specialCardEmoji', 'labelHand', 
    'labelHandSum', 'labelSpecialBonus', 'labelSubTotal', 'labelCombos', 
    'iconCombo', 'labelUnused', 'iconUnused', 'labelTotal'
];

// Speichern nur für Admins
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $themeName = $_POST['themeName'];
    $themes[$themeName] = $_POST['config'];
    file_put_contents($jsonFile, json_encode($themes, JSON_PRETTY_PRINT));
    header("Location: " . $_SERVER['PHP_SELF'] . "?theme=" . urlencode($themeName));
    exit;
}

$currentThemeName = $_GET['theme'] ?? (array_key_first($themes) ?? 'default');
$currentConfig = $themes[$currentThemeName] ?? [];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Shine-Snatch <?= $is_logged_in ? 'Editor' : 'Viewer' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/nano.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
    /* Kleiner Fix für das Design des Buttons */
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
    .text-muted { color: #bdbdbd !important; /* Hier deine Wunschfarbe für die Anleitung */ }
    /* Erlaubt der linken Seite zu scrollen, während die rechte stehen bleibt */
.sticky-column {
    position: sticky;
    top: 20px;
    height: calc(100vh - 60px); /* Höhe des Sichtfensters minus Padding */
}

.scrollable-content {
    max-height: calc(100vh - 80px);
    overflow-y: auto;
    padding-right: 15px; /* Platz für den Scrollbalken */
}

/* Optional: Hübscherer Scrollbar für ein modernes dunkles Design */
.scrollable-content::-webkit-scrollbar {
    width: 8px;
}
.scrollable-content::-webkit-scrollbar-track {
    background: #1e1e1e;
}
.scrollable-content::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 4px;
}
.scrollable-content::-webkit-scrollbar-thumb:hover {
    background: #daa520;
}

/* Das Eingabefeld, das gerade "gehovert" wird */
.highlight-input {
    background-color: #3b310d !important; /* Dunkles Gold/Gelb */
    border-color: #daa520 !important;
    box-shadow: 0 0 10px rgba(218, 165, 20, 0.5);
    transition: all 0.2s ease;
    transform: scale(1.02);
}

/* Optional: Zeiger-Feedback in der Vorschau */
[data-edit-key] {
    cursor: help;
}
.highlight-input {
    transition: background-color 0.3s ease, transform 0.2s ease;
}


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
                                <option value="<?= $name ?>" <?= $name == $currentThemeName ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary" onclick="createNew()">Neu</button>
                    </div>
                </form>

                <form id="themeForm" method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" id="themeNameInput" name="themeName" value="<?= $currentThemeName ?>">
                    <div class="row">
                        <div class="col-6">
                            <h5>Farben</h5>
                                <?php foreach ($colorFields as $field): ?>
                                    <label><?= $field ?>:</label>
                                    <div class="color-picker-group mb-2">
                                        <div id="picker-<?= $field ?>"></div>
                                        <input type="text" name="config[<?= $field ?>]" id="<?= $field ?>" 
                                            class="form-control form-control-sm color-input" 
                                            value="<?= $currentConfig[$field] ?? 'rgba(0,0,0,1)' ?>" 
                                            oninput="updatePreview()">
                                    </div>
                                <?php endforeach; ?>
                        </div>
                        <div class="col-6">
                            <h5>Texte & Icons</h5>
                            <?php 
                            foreach ($textFields as $field): ?>
                                <label><?= $field ?>:</label>
                                <input type="text" name="config[<?= $field ?>]" id="<?= $field ?>" class="form-control form-control-sm" value="<?= $currentConfig[$field] ?? '' ?>" oninput="updatePreview()">
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mt-4 mb-5">Speichern</button>
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
    <button class="btn btn-warning btn-sm" id="copyMacroBtn" onclick="copyMacroToClipboard()" style="white-space: nowrap; min-width: 160px; font-weight: bold;">
        📋 Script kopieren
    </button>
</div>
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
                <a href="shine-snatch_rules.html" target="_blank" style="text-decoration: none;">https://<?php echo $_SERVER['HTTP_HOST']; ?>/shine-snatch/shine-snatch_rules.html</a>
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
                <code class="text-info" style="word-break: break-all;"><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/dnd/items/Krark/shine-snatch.webp">https://<?php echo $_SERVER['HTTP_HOST']; ?>/dnd/items/Krark/shine-snatch.webp</a></code>
            </li>
            <li class="mb-1">Dann noch speichern und <strong>viel Glück beim Spielen</strong></li>
        </ol>
    </div>
</div>


                
    <p class="text-muted">Wähle ein Theme aus oder kopiere den Namen:</p>
    <div id="themeSelector">
        <?php foreach ($themes as $name => $cfg): ?>
            <div class="theme-card w-100 <?= $name == $currentThemeName ? 'active' : '' ?>" 
                 onclick="document.getElementById('radio_<?= md5($name) ?>').click()">
                
                <input type="radio" id="radio_<?= md5($name) ?>" name="viewerTheme" class="hidden-radio" 
                       value="<?= htmlspecialchars(json_encode($cfg)) ?>" 
                       onchange="selectViewerTheme(this, '<?= htmlspecialchars($name) ?>')">
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span style="font-size: 1.2em; margin-right: 8px;"><?= $cfg['headerIcon'] ?></span>
                        <strong style="color: <?= $cfg['colorPrimary'] ?>;"><?= htmlspecialchars($name) ?></strong>
                    </div>
                    
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge" style="background: <?= $cfg['colorBg'] ?>; color: <?= $cfg['colorBoltCore'] ?>; border: 1px solid rgba(255,255,255,0.2);">
                            Vorschau
                        </span>
                        
                        <button class="btn btn-sm btn-outline-light" 
                                style="padding: 2px 6px; font-size: 0.8em; border-color: #444;"
                                onclick="copyToClipboard(event, '<?= htmlspecialchars($name) ?>')" 
                                title="Name kopieren">
                            📋
                        </button>
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
    
    <div class="card bg-dark border-secondary mb-4 p-3 shadow-lg" style="max-width: 500px; margin: 0 auto;">
        <div class="row g-3">
            <div class="col-12">
                <label class="small text-muted mb-1 d-block">Gezogene Karten (IDs 1-60):</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-secondary border-secondary text-white" style="width: 40px; justify-content: center;">🃏</span>
                    <input type="text" id="testHand" class="form-control" value="20,57,16,37,27" oninput="updatePreview()">
                    <button class="btn btn-outline-warning px-3" onclick="randomizeHand()" title="Zufällige Hand">🎲</button>
                </div>
            </div>

            <div class="col-12">
                <label class="small text-muted mb-1 d-block">Deine Sammelkarten: (hier nur für die Vorschau)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-secondary border-secondary text-white" style="width: 40px; justify-content: center;">🌟</span>
                    <input type="text" id="testOwned" class="form-control" value="20,16,27" oninput="updatePreview()">
                    <button class="btn btn-outline-warning px-3" onclick="randomizeOwned()" title="Zufällige Sammlung">🎲</button>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <button class="btn btn-primary btn-sm w-100 shadow-sm" style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px;" onclick="updatePreview()">
                🔄 Vorschau aktualisieren
            </button>
        </div>
    </div>
    

    <div class="d-flex justify-content-center">
        <div id="previewArea" class="preview-pane">
            </div>
    </div>
    </div>
</div>
    </div>
</div>

<script>


// Für den Viewer-Modus: Theme-Daten global halten
let currentViewerConfig = <?= json_encode($currentConfig) ?>;

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

        // 2. Das aktuell ausgewählte Theme im Script ersetzen (optional, aber extrem komfortabel!)
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

document.addEventListener('DOMContentLoaded', () => {
    const colorFields = ['colorPrimary', 'colorGlowMain', 'colorBoltCore', 'colorAccent', 'colorBg', 'colorBgCard', 'shadowColor', 'colorSpecialBg', 'colorTextMain', 'colorTextMuted', 'colorTextComboIds'];

    colorFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input) return;

        // Pickr Initialisierung
        const pickr = Pickr.create({
            el: `#picker-${field}`,
            theme: 'nano', // 'classic', or 'monolith', or 'nano'
            default: input.value || '#000000',
            components: {
                preview: true,
                opacity: true, // Erlaubt Transparenz (RGBA)
                hue: true,
                interaction: {
                    input: true,
                    save: true
                }
            },
            strings: { save: 'OK' }
        });

        // Wenn die Farbe im Picker geändert wird
        pickr.on('change', (color) => {
            const rgbaString = color.toRGBA().toString(0); // 0 dezimalstellen
            input.value = rgbaString;
            updatePreview();
        });

        // Wenn im Textfeld getippt wird, Picker Farbe aktualisieren
        input.addEventListener('change', () => {
            pickr.setColor(input.value);
        });
    });
});

function selectViewerTheme(radio, name) {
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    radio.parentElement.classList.add('active');
    currentViewerConfig = JSON.parse(radio.value);
    updatePreview();
}

function copyToClipboard(event, text) {
    // Verhindert, dass das Theme gewechselt wird, wenn man nur kopieren will
    if(event) event.stopPropagation();
    
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget; // Nutze currentTarget für den Button selbst
        const oldContent = btn.innerHTML;
        btn.innerHTML = "✅";
        btn.classList.replace('btn-outline-light', 'btn-success');
        
        setTimeout(() => { 
            btn.innerHTML = oldContent; 
            btn.classList.replace('btn-success', 'btn-outline-light');
        }, 1000);
    });
}

// Zufällige Hand generieren (5 Karten zwischen 1 und 60)
function randomizeHand() {
    let ids = [];
    while(ids.length < 5) {
        let r = Math.floor(Math.random() * 60) + 1;
        if(!ids.includes(r)) ids.push(r);
    }
    document.getElementById('testHand').value = ids.join(',');
    updatePreview();
}

// Zufällige Sammelkarten (0 bis 10 Stück)
function randomizeOwned() {
    let count = Math.floor(Math.random() * 11);
    let ids = [];
    while(ids.length < count) {
        let r = Math.floor(Math.random() * 60) + 1;
        if(!ids.includes(r)) ids.push(r);
    }
    document.getElementById('testOwned').value = ids.join(',');
    updatePreview();
}

async function updatePreview() {
    const previewArea = document.getElementById('previewArea');
    previewArea.style.opacity = "0.5"; // Lade-Effekt

    // Daten aus dem Editor sammeln
    let themeConfig = {};
    if (document.getElementById('themeForm')) {
        document.querySelectorAll('#themeForm input').forEach(input => {
            if(input.name.startsWith('config[')) {
                const key = input.name.match(/\[(.*?)\]/)[1];
                themeConfig[key] = input.value;
            }
        });
    } else {
        themeConfig = currentViewerConfig;
    }

    // Test-Daten für Karten sammeln
    const handIds = document.getElementById('testHand').value.split(',').map(Number);
    const ownedIds = document.getElementById('testOwned').value.split(',').map(Number);

    try {
        const serverUrl = window.location.origin;
        const response = await fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/shine-snatch/shine-snatch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                theme: "PREVIEW_MODE", // Signal an PHP
                customConfig: themeConfig, // Schickt die aktuellen Editor-Farben mit!
                overrideHand: handIds,
                ownedCards: ownedIds,
                world: "Theme-Editor", 
                version: "" , 
                url: serverUrl
            })
        });

        const result = await response.json();
        previewArea.innerHTML = result.html;
    } catch (e) {
        previewArea.innerHTML = "<p class='text-danger p-3'>Fehler beim Laden der Vorschau. Prüfe die shine-snatch.php Verbindung.</p>";
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

// Navigation mit Pfeiltasten
document.addEventListener('keydown', function(e) {
    // Prüfen, ob der User gerade in einem Textfeld schreibt (dann Navigation deaktivieren)
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
        return;
    }

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); // Verhindert das Scrollen der Seite

        const isAdmin = <?= json_encode($is_logged_in) ?>;
        
        if (isAdmin) {
            // ADMIN LOGIK: Navigiere in der Select-Box
            const select = document.querySelector('select[name="theme"]');
            if (select) {
                let currentIndex = select.selectedIndex;
                if (e.key === 'ArrowDown' && currentIndex < select.options.length - 1) {
                    select.selectedIndex = currentIndex + 1;
                } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                    select.selectedIndex = currentIndex - 1;
                }
                select.dispatchEvent(new Event('change')); // Trigger die Formular-Absendung
            }
        } else {
            // GALLERY LOGIK: Navigiere durch die Karten
            const cards = Array.from(document.querySelectorAll('.theme-card'));
            const activeCard = document.querySelector('.theme-card.active');
            let currentIndex = cards.indexOf(activeCard);

            if (e.key === 'ArrowDown' && currentIndex < cards.length - 1) {
                cards[currentIndex + 1].click();
                cards[currentIndex + 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                cards[currentIndex - 1].click();
                cards[currentIndex - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }
});

// Wir nutzen "mouseover" auf der previewArea, um Elemente mit data-edit-key zu finden
document.getElementById('previewArea').addEventListener('mouseover', (e) => {
    const target = e.target.closest('[data-edit-keys]');
    if (!target) return;

    // Hole den String "colorBg,colorBoltCore,..." und mache ein Array daraus
    const keys = target.getAttribute('data-edit-keys').split(',');
    
    document.querySelectorAll('.highlight-input').forEach(el => el.classList.remove('highlight-input'));

    keys.forEach(key => {
        const inputField = document.getElementById(key.trim());
        if (inputField) {
            inputField.classList.add('highlight-input');
        }
    });
});

// Highlight entfernen, wenn die Maus die Vorschau verlässt
document.getElementById('previewArea').addEventListener('mouseout', () => {
    document.querySelectorAll('.highlight-input').forEach(el => el.classList.remove('highlight-input'));
});


// Sicherer Check, ob wir uns im Gallery-Modus befinden
const collapseElement = document.getElementById('installGuide');
const collapseIcon = document.getElementById('collapseIcon');

// Nur ausführen, wenn beide Elemente existieren
if (collapseElement && collapseIcon) {
    collapseElement.addEventListener('show.bs.collapse', () => {
        collapseIcon.innerText = '▲';
    });
    collapseElement.addEventListener('hide.bs.collapse', () => {
        collapseIcon.innerText = '▼';
    });
}

// Initialer Start
updatePreview();
</script>
</body>
</html>