<?php
session_start();
$config = require "config.php";
$admin_password_hash = $config["admin_password_hash"];

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: card_edit.php");
    exit();
}

if (isset($_POST["login_pass"])) {
    if (password_verify($_POST["login_pass"], $admin_password_hash)) {
        $_SESSION["loggedin"] = true;
    } else {
        $error = "Falsches Passwort!";
    }
}

function getDynamicStyle($id)
{
    if (empty($id)) {
        return "";
    }
    $prefix = substr((string) $id, 0, 3);

    $h = 0;
    foreach (str_split($prefix) as $char) {
        $h = ($h << 5) - $h + ord($char);
    }

    $hue = abs($h % 360);
    return "style='border-color: hsl($hue, 70%, 50%); background: hsl($hue, 70%, 15%);'";
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true): ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login - Card Editor</title>
    <style>
        body { background: #121212; color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #1e1e1e; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); text-align: center; border: 1px solid #333; }
        input { padding: 10px; width: 200px; border-radius: 5px; border: 1px solid #444; background: #252525; color: white; margin-bottom: 10px; }
        button { padding: 10px 20px; background: #bb86fc; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔒 Card Editor Login</h2>
        <?php if (isset($error)) {
            echo "<p style='color:#cf6679'>$error</p>";
        } ?>
        <form method="POST">
            <input type="password" name="login_pass" placeholder="Passwort" autofocus><br>
            <button type="submit">Einloggen</button>
        </form>
    </div>
</body>
</html>
<?php exit();endif;

// Zentrale DB-Verbindung laden
require_once "db.php";
$pdo = getDatabaseConnection();
$message = "";

// --- DATEN SPEICHERN ---
if (isset($_POST["save"])) {
    try {
        $pdo->beginTransaction();

        // 1. Kartentypen aktualisieren
        $stmtCard = $pdo->prepare("INSERT INTO snatch_game_card_types (id, emoji, name, count, points, start_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE emoji=VALUES(emoji), name=VALUES(name), count=VALUES(count), points=VALUES(points), start_id=VALUES(start_id)");

        foreach ($_POST["cards"] ?? [] as $c) {
            if (empty($c["id"])) {
                continue;
            }
            $stmtCard->execute([
                $c["id"],
                $c["emoji"],
                $c["name"],
                (int) $c["count"],
                (int) $c["points"],
                (int) $c["startId"],
            ]);
        }

        // 2. Gruppen aktualisieren
        $pdo->exec("DELETE FROM snatch_game_groups");
        $stmtGroup = $pdo->prepare(
            "INSERT INTO snatch_game_groups (id, cards) VALUES (?, ?)",
        );
        foreach ($_POST["groups"] ?? [] as $g) {
            if (empty($g["id"])) {
                continue;
            }
            $groupCardsArray = array_values(array_filter($g["cards"] ?? []));
            $stmtGroup->execute([
                $g["id"],
                json_encode($groupCardsArray, JSON_UNESCAPED_UNICODE),
            ]);
        }

        // 3. Kombinationen aktualisieren
        $pdo->exec("DELETE FROM snatch_game_combos");

        $stmtCombo = $pdo->prepare(
            "INSERT INTO snatch_game_combos (id, name, emoji, points, needs, cat) VALUES (?, ?, ?, ?, ?, ?)",
        );
        $comboIdCounter = 1;

        foreach ($_POST["combos"] ?? [] as $cb) {
            if (empty($cb["name"])) {
                continue;
            }

            $needsArray = array_values(array_filter($cb["needs"] ?? []));
            $needsString = json_encode($needsArray, JSON_UNESCAPED_UNICODE);

            $stmtCombo->execute([
                $comboIdCounter++,
                $cb["name"],
                $cb["emoji"],
                (int) $cb["points"],
                $needsString,
                $cb["cat"],
            ]);
        }

        $pdo->commit();
        $message =
            "✅ Änderungen erfolgreich direkt in den SQL-Tabellen gespeichert!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "❌ Fehler beim Speichern: " . $e->getMessage();
    }
}

// --- DATEN AUS DEN ECHTEN SQL-TABELLEN LADEN ---

// 1. Kartentypen holen (KORREKTUR: Sortiert nach start_id für das Modal!)
$cardTypes = $pdo
    ->query(
        "SELECT id, emoji, name, count, points, start_id AS startId FROM snatch_game_card_types ORDER BY start_id ASC, id ASC",
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// 2. Gruppen holen
$dbGroups = $pdo
    ->query("SELECT id, cards FROM snatch_game_groups ORDER BY id ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
$groups = [];
foreach ($dbGroups as $g) {
    $decoded = !empty($g["cards"]) ? json_decode($g["cards"], true) : [];
    $g["cards"] = is_array($decoded) ? $decoded : [];
    $groups[] = $g;
}

// 3. Kombinationen holen
$dbCombos = $pdo
    ->query(
        "SELECT name, emoji, points, needs, cat FROM snatch_game_combos ORDER BY id ASC",
    )
    ->fetchAll(PDO::FETCH_ASSOC);
$combos = [];
foreach ($dbCombos as $cb) {
    $decoded = !empty($cb["needs"]) ? json_decode($cb["needs"], true) : [];
    $cb["needs"] = is_array($decoded) ? $decoded : [];
    $combos[] = $cb;
}

// 4. Themes für das Dropdown laden
$themesList = $pdo
    ->query(
        "SELECT theme_name, icon_combo AS headerIcon FROM snatch_themes ORDER BY theme_name ASC",
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Hilfs-Arrays für getrennte Optionen im Picker übergeben
$pickerCards = [];
foreach ($cardTypes as $ct) {
    $pickerCards[] = [
        "id" => $ct["id"],
        "name" => $ct["name"],
        "emoji" => $ct["emoji"],
        "type" => "card",
    ];
}
$pickerGroups = [];
foreach ($groups as $g) {
    $pickerGroups[] = [
        "id" => $g["id"],
        "name" => "Gruppe: " . $g["id"],
        "emoji" => "📁",
        "type" => "group",
    ];
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Snatch Database Editor Pro</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        :root {
            --bg: #050705; --panel: rgba(20, 25, 20, 0.7); --primary: #2cb24c;
            --accent: #a855f7; --text: #e2e8f0; --danger: #ef4444; --card-bg: rgba(255,255,255,0.05);
        }
        body { font-family: 'Signika', sans-serif; background: var(--bg); color: var(--text); padding: 20px; margin-bottom: 100px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: var(--panel); backdrop-filter: blur(10px); border-radius: 20px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        h2 { color: var(--accent); display: flex; justify-content: space-between; align-items: center; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px; color: var(--text); opacity: 0.6; font-weight: 300; font-size: 0.8rem; }
        td { padding: 8px; vertical-align: top; border-bottom: 1px solid rgba(255,255,255,0.03); }
        input { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px; border-radius: 8px; width: 100%; box-sizing: border-box; }
        .selection-container { display: flex; flex-wrap: wrap; gap: 5px; min-height: 40px; align-items: center; }
        .select-btn {
            background: var(--card-bg); border: 1px dashed rgba(255,255,255,0.2); color: var(--text);
            padding: 5px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: 0.2s;
            display: flex; align-items: center; gap: 5px;
        }
        .select-btn:hover { background: rgba(255,255,255,0.15); border-color: var(--primary); }
        .select-btn.filled { border-style: solid; border-color: var(--primary); background: rgba(44, 178, 76, 0.1); }
        #selectionModal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); backdrop-filter: blur(5px); }
        .modal-content { background: #1a1a1a; margin: 5% auto; padding: 25px; border-radius: 20px; width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; border: 1px solid var(--accent); }
        .modal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-top: 10px; }
        .modal-item { background: #252525; padding: 10px; border-radius: 10px; cursor: pointer; text-align: center; border: 1px solid transparent; transition: 0.2s; }
        .modal-item:hover { border-color: var(--primary); background: #303030; }
        .modal-item.empty { border-color: var(--danger); color: var(--danger); grid-column: 1 / -1; }
        .modal-section-title { font-size: 0.9rem; color: var(--accent); margin-top: 25px; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 10px; cursor: pointer; }
        .btn-del { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); padding: 8px; border-radius: 8px; cursor: pointer; }
        .save-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; border: none; padding: 15px 50px; border-radius: 50px; font-weight: 600; cursor: pointer; box-shadow: 0 10px 40px rgba(0,0,0,0.5); z-index: 100; }
        .msg { background: rgba(44, 178, 76, 0.2); color: #fff; padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; border: 1px solid var(--primary); }
        .search-input { margin-bottom: 15px; font-size: 1.1rem; padding: 12px; }
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        .modal-item.active { border: 2px solid var(--primary); background: rgba(44, 178, 76, 0.2); box-shadow: 0 0 10px rgba(44, 178, 76, 0.3); }
        .sortable-ghost { opacity: 0.4; background: var(--accent) !important; }
        .drag-handle:hover { color: var(--primary) !important; }
        .nav-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 4px;
            font-family: 'Georgia', serif;
            font-size: 14px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        /* Stil für den Zufallsnamen-Button */
        .btn-accent {
            background: #2a2a2a;
            color: #e67e22; /* Oder deine var(--accent) */
            border: 1px solid #3a2f1d;
        }
        .btn-accent:hover {
            background: #3a2f1d;
            color: #fff;
            border-color: #e67e22;
        }

        /* Stil für den Logout-Button */
        .btn-danger {
            background: #1e1e1e;
            color: #c0392b; /* Oder deine var(--danger) */
            border: 1px solid #333;
        }
        .btn-danger:hover {
            background: #c0392b;
            color: #fff;
            border-color: #ff4d4d;
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content: space-between; align-items: center;">
        <h1>SNATCH DATABASE CONTROL</h1>
        <div class="nav-container">
            <a href="cardnames.php" class="nav-btn btn-accent">
                📖 Zusätze
            </a>
            <a href="newcard.php" class="nav-btn btn-accent">
                🃏 Karten
            </a>
            <a href="outputtexts.php" class="nav-btn btn-accent">
                💬 Texte
            </a>

            <a href="?logout=1" class="nav-btn btn-danger">
                🚪 Verlassen
            </a>
        </div>
    </div>

    <?php if ($message) {
        echo "<div class='msg'>$message</div>";
    } ?>

    <form method="POST" id="dbForm">
        <!-- SEKTION 1: KARTEN-TYPEN -->
        <div class="section">
            <h2>Karten-Typen <button type="button" class="btn-add" onclick="addRow('cardsTable')">+ Neu</button></h2>
            <table id="cardsTable">
                <thead><tr><th>ID (String)</th><th>Emoji</th><th>Name</th><th>Anz.</th><th>Pkt.</th><th>StartId</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($cardTypes as $idx => $ct): ?>
                    <tr>
                        <td><input type="text" name="cards[<?= $idx ?>][id]" value="<?= htmlspecialchars(
    $ct["id"],
) ?>" style="width:90px; color:var(--primary); text-align:center;"></td>
                        <td><input type="text" name="cards[<?= $idx ?>][emoji]" value="<?= htmlspecialchars(
    $ct["emoji"],
) ?>" style="width:50px; text-align:center;"></td>
                        <td><input type="text" name="cards[<?= $idx ?>][name]" value="<?= htmlspecialchars(
    $ct["name"],
) ?>"></td>
                        <td><input type="number" name="cards[<?= $idx ?>][count]" value="<?= $ct[
    "count"
] ?>" style="width:60px;"></td>
                        <td><input type="number" name="cards[<?= $idx ?>][points]" value="<?= $ct[
    "points"
] ?>" style="width:60px;"></td>
                        <td><input type="number" name="cards[<?= $idx ?>][startId]" value="<?= $ct[
    "startId"
] ?>" style="width:70px;"></td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SEKTION 2: KARTEN-GRUPPEN -->
        <div class="section">
            <h2>Karten-Gruppen <button type="button" class="btn-add" onclick="addRow('groupsTable')">+ Neu</button></h2>
            <table id="groupsTable">
                <thead><tr><th>Gruppen ID (z.B. ANY_LEG)</th><th>Enthaltene Karten (Max 12)</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($groups as $idx => $g): ?>
                    <tr>
                        <td><input type="text" name="groups[<?= $idx ?>][id]" value="<?= htmlspecialchars(
    $g["id"],
) ?>" style="width:150px; color:var(--accent); font-weight:bold;"></td>
                        <td>
                            <div class="selection-container" data-type="group" data-row="<?= $idx ?>" data-max="12">
                                <?php
                                foreach ($g["cards"] ?? [] as $val):

                                    $displayEmoji = "🃏";
                                    foreach ($cardTypes as $ct) {
                                        if ($ct["id"] == $val) {
                                            $displayEmoji = $ct["emoji"];
                                            break;
                                        }
                                    }
                                    ?>
                                    <button type="button" class="select-btn filled" <?= getDynamicStyle(
                                        $val,
                                    ) ?> onclick="openPicker(this)">
                                        <span class="label"><?= $displayEmoji ?> <?= htmlspecialchars(
     $val,
 ) ?></span>
                                        <input type="hidden" name="groups[<?= $idx ?>][cards][]" value="<?= htmlspecialchars(
    $val,
) ?>">
                                    </button>
                                <?php
                                endforeach;
                                if (count($g["cards"] ?? []) < 12): ?>
                                    <button type="button" class="select-btn" onclick="openPicker(this)">
                                        <span class="label">+ Wählen</span>
                                        <input type="hidden" name="groups[<?= $idx ?>][cards][]" value="">
                                    </button>
                                <?php endif;
                                ?>
                            </div>
                        </td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SEKTION 3: KOMBINATIONEN -->
        <div class="section">
            <h2>Kombinationen <button type="button" class="btn-add" onclick="addRow('combosTable')">+ Neu</button></h2>
            <table id="combosTable">
                <thead><tr><th width="30">↕</th><th>Emoji</th><th>Name</th><th>Pkt.</th><th>Bedarf (Karten / Gruppen - Max 5)</th><th>Kategorie-Theme</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($combos as $idx => $cb): ?>
                    <tr class="sortable-row">
                        <td class="drag-handle" style="cursor: grab; color: var(--accent); text-align: center;">☰</td>
                        <td><input type="text" name="combos[<?= $idx ?>][emoji]" value="<?= htmlspecialchars(
    $cb["emoji"],
) ?>" style="width:70px; text-align:center;"></td>
                        <td><input type="text" name="combos[<?= $idx ?>][name]" value="<?= htmlspecialchars(
    $cb["name"],
) ?>"></td>
                        <td><input type="number" name="combos[<?= $idx ?>][points]" value="<?= $cb[
    "points"
] ?>" style="width:60px;"></td>
                        <td>
                            <div class="selection-container" data-type="combo" data-row="<?= $idx ?>" data-max="5">
                                <?php
                                foreach ($cb["needs"] ?? [] as $val):

                                    $displayEmoji = "📁";
                                    foreach ($cardTypes as $ct) {
                                        if ($ct["id"] == $val) {
                                            $displayEmoji = $ct["emoji"];
                                            break;
                                        }
                                    }
                                    foreach ($groups as $g) {
                                        if ($g["id"] == $val) {
                                            $displayEmoji = "📁";
                                            break;
                                        }
                                    }
                                    ?>
                                    <button type="button" class="select-btn filled" <?= getDynamicStyle(
                                        $val,
                                    ) ?> onclick="openPicker(this)">
                                        <span class="label"><?= $displayEmoji ?> <?= htmlspecialchars(
     $val,
 ) ?></span>
                                        <input type="hidden" name="combos[<?= $idx ?>][needs][]" value="<?= htmlspecialchars(
    $val,
) ?>">
                                    </button>
                                <?php
                                endforeach;
                                if (count($cb["needs"] ?? []) < 5): ?>
                                    <button type="button" class="select-btn" onclick="openPicker(this)">
                                        <span class="label">+ Wählen</span>
                                        <input type="hidden" name="combos[<?= $idx ?>][needs][]" value="">
                                    </button>
                                <?php endif;
                                ?>
                            </div>
                        </td>
                        <td>
                            <select name="combos[<?= $idx ?>][cat]" style="width: 100%; background: rgba(0,0,0,0.4); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 8px; border-radius: 8px;">
                                <option value="">-- Kein Theme --</option>
                                <?php foreach ($themesList as $t): ?>
                                    <option value="<?= htmlspecialchars(
                                        $t["theme_name"],
                                    ) ?>" <?= isset($cb["cat"]) &&
$cb["cat"] == $t["theme_name"]
    ? "selected"
    : "" ?>>
                                        <?= htmlspecialchars(
                                            $t["theme_name"],
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" name="save" class="save-bar">ÄNDERUNGEN SPEICHERN</button>
    </form>
</div>

<div id="selectionModal">
    <div class="modal-content">
        <h3 id="modalTitle">Auswahl treffen</h3>
        <input type="text" id="modalSearch" class="search-input" placeholder="Suchen..." oninput="filterModal()">

        <div id="modalClearContainer"></div>

        <div class="modal-section-title" id="cardsTitle">🃏 Karten (Nach Start-ID sortiert)</div>
        <div class="modal-grid" id="modalCardsGrid"></div>

        <div class="modal-section-title" id="groupsTitleSection" style="color: var(--accent);">📁 Gruppen / Aliase</div>
        <div class="modal-grid" id="modalGroupsGrid"></div>

        <div style="margin-top: 30px; text-align: right;">
            <button type="button" class="btn-add" style="background:#444" onclick="closePicker()">Abbrechen</button>
        </div>
    </div>
</div>

<script>
let currentTargetBtn = null;
const pickerCards = <?= json_encode($pickerCards) ?>;
const pickerGroups = <?= json_encode($pickerGroups) ?>;
const availableThemes = <?= json_encode($themesList) ?>;

function initSortable() {
    const el = document.querySelector('#combosTable tbody');
    Sortable.create(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost'
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSortable();
});

function addRow(tableId) {
    const table = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const idx = Date.now();
    let row = document.createElement('tr');

    if (tableId === 'cardsTable') {
        row.innerHTML = `
            <td><input type="text" name="cards[${idx}][id]" style="width:90px; color:var(--primary); text-align:center;"></td>
            <td><input type="text" name="cards[${idx}][emoji]" style="width:50px; text-align:center;"></td>
            <td><input type="text" name="cards[${idx}][name]"></td>
            <td><input type="number" name="cards[${idx}][count]" value="0" style="width:60px;"></td>
            <td><input type="number" name="cards[${idx}][points]" value="0" style="width:60px;"></td>
            <td><input type="number" name="cards[${idx}][startId]" value="0" style="width:70px;"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>`;
    } else if (tableId === 'groupsTable') {
        row.innerHTML = `
            <td><input type="text" name="groups[${idx}][id]" style="width:150px; color:var(--accent); font-weight:bold;"></td>
            <td><div class="selection-container" data-type="group" data-row="${idx}" data-max="12">
                <button type="button" class="select-btn" onclick="openPicker(this)">
                    <span class="label">+ Wählen</span>
                    <input type="hidden" name="groups[${idx}][cards][]" value="">
                </button>
            </div></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>`;
    } else if (tableId === 'combosTable') {
        let themeOptions = '<option value="">-- Kein Theme --</option>';
        availableThemes.forEach(t => {
            themeOptions += `<option value="${t.theme_name}">${t.theme_name}</option>';`;
        });

        row.innerHTML = `
            <td class="drag-handle" style="cursor: grab; color: var(--accent); text-align: center;">☰</td>
            <td><input type="text" name="combos[${idx}][emoji]" style="width:70px; text-align:center;"></td>
            <td><input type="text" name="combos[${idx}][name]"></td>
            <td><input type="number" name="combos[${idx}][points]" value="0" style="width:60px;"></td>
            <td><div class="selection-container" data-type="combo" data-row="${idx}" data-max="5">
                <button type="button" class="select-btn" onclick="openPicker(this)">
                    <span class="label">+ Wählen</span>
                    <input type="hidden" name="combos[${idx}][needs][]" value="">
                </button>
            </div></td>
            <td>
                <select name="combos[${idx}][cat]" style="width: 100%; background: rgba(0,0,0,0.4); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 8px; border-radius: 8px;">
                    ${themeOptions}
                </select>
            </td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>`;
    }
    table.appendChild(row);
}

function removeRow(btn) {
    if (confirm('Eintrag löschen?')) btn.closest('tr').remove();
}

function openPicker(btn) {
    currentTargetBtn = btn;
    const container = btn.closest('.selection-container');
    const modeType = container.dataset.type; // 'group' oder 'combo'
    const modal = document.getElementById('selectionModal');
    const clearContainer = document.getElementById('modalClearContainer');
    const cardsGrid = document.getElementById('modalCardsGrid');
    const groupsGrid = document.getElementById('modalGroupsGrid');
    const groupsTitle = document.getElementById('groupsTitleSection');
    const currentValue = btn.querySelector('input').value;

    clearContainer.innerHTML = '';
    cardsGrid.innerHTML = '';
    groupsGrid.innerHTML = '';

    // Lösch-Button oben anheften
    const emptyDiv = document.createElement('div');
    emptyDiv.className = 'modal-item empty' + (currentValue === '' ? ' active' : '');
    emptyDiv.onclick = () => selectOption('');
    emptyDiv.innerHTML = '❌ LEEREN / LÖSCHEN';
    clearContainer.appendChild(emptyDiv);

    // 1. Karten hinzufügen (Sind per PHP bereits nach startId sortiert)
    pickerCards.forEach(c => {
        const item = document.createElement('div');
        item.className = 'modal-item' + (currentValue === c.id ? ' active' : '');
        item.onclick = () => selectOption(c.id);
        item.innerHTML = `${c.emoji} ${c.id} <br><small>${c.name}</small>`;
        cardsGrid.appendChild(item);
    });

    // 2. Gruppen hinzufügen (NUR wenn wir im Combo-Modus sind!)
    if (modeType === 'combo') {
        groupsTitle.style.display = 'block';
        groupsGrid.style.display = 'grid';

        pickerGroups.forEach(g => {
            const item = document.createElement('div');
            item.style.borderColor = 'var(--accent)';
            item.className = 'modal-item' + (currentValue === g.id ? ' active' : '');
            item.onclick = () => selectOption(g.id);
            item.innerHTML = `${g.emoji} ${g.id}`;
            groupsGrid.appendChild(item);
        });
    } else {
        // Gruppen ausblenden, wenn Karten für eine Gruppe ausgewählt werden
        groupsTitle.style.display = 'none';
        groupsGrid.style.display = 'none';
    }

    modal.style.display = 'block';
    const searchInput = document.getElementById('modalSearch');
    searchInput.value = '';
    filterModal();
    searchInput.focus();

    setTimeout(() => {
        const activeItem = document.querySelector('.modal-item.active');
        if (activeItem) activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 10);
}

function closePicker() {
    document.getElementById('selectionModal').style.display = 'none';
}

function filterModal() {
    const q = document.getElementById('modalSearch').value.toLowerCase();
    document.querySelectorAll('.modal-grid .modal-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(q) ? 'block' : 'none';
    });
}

function selectOption(val) {
    const btn = currentTargetBtn;
    const container = btn.closest('.selection-container');
    const max = parseInt(container.dataset.max);
    btn.querySelector('input').value = val;
    refreshSelectionFields(container, max);
    closePicker();
}

function refreshSelectionFields(container, max) {
    const buttons = Array.from(container.querySelectorAll('.select-btn'));
    const values = buttons.map(b => b.querySelector('input').value).filter(v => v !== '');

    container.innerHTML = '';
    const type = container.dataset.type;
    const rowIdx = container.dataset.row;
    const fieldName = (type === 'group') ? 'cards' : 'needs';

    values.forEach(v => {
        const foundCard = pickerCards.find(o => o.id == v);
        const foundGroup = pickerGroups.find(o => o.id == v);
        const emoji = foundCard ? foundCard.emoji : (foundGroup ? '📁' : '🃏');
        addSelectionButton(container, type, rowIdx, fieldName, v, emoji);
    });

    if(values.length < max) {
        addSelectionButton(container, type, rowIdx, fieldName, '', '');
    }
}

function getDynamicColorStyle(id) {
    if (!id || id === "") return "";
    const prefix = String(id).substring(0, 3);
    let h = 0;
    for (let i = 0; i < prefix.length; i++) {
        h = ((h << 5) - h) + prefix.charCodeAt(i);
        h |= 0;
    }
    const hue = Math.abs(h % 360);
    return `border-color: hsl(${hue}, 70%, 50%); background: hsl(${hue}, 70%, 15%);`;
}

function addSelectionButton(container, type, rowIdx, fieldName, value, emoji) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'select-btn ' + (value ? 'filled' : '');
    if (value) btn.setAttribute('style', getDynamicColorStyle(value));
    btn.onclick = function() { openPicker(this); };
    const displayLabel = value ? `${emoji} ${value}` : '+ Wählen';
    btn.innerHTML = `<span class="label">${displayLabel}</span>
                     <input type="hidden" name="${type}s[${rowIdx}][${fieldName}][]" value="${value}">`;
    container.appendChild(btn);
}

window.onclick = function(event) {
    if (event.target == document.getElementById('selectionModal')) closePicker();
}
</script>
</body>
</html>
