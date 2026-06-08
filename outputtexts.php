<?php
// outputtexts.php

// 1. db.php einbinden
if (file_exists("db.php")) {
    include "db.php";
} else {
    // Falls die Datei in einem anderen Verzeichnis liegt, Pfad anpassen:
    die("❌ Fehler: db.php wurde nicht im aktuellen Verzeichnis gefunden!");
}

// 2. Die PDO-Verbindung über die Funktion aus der db.php holen!
if (function_exists("getDatabaseConnection")) {
    $pdo = getDatabaseConnection();
} else {
    die(
        "❌ Fehler: Die Funktion getDatabaseConnection() existiert nicht in deiner db.php!"
    );
}

// Sicherheits-Check: Falls getDatabaseConnection() aus irgendeinem Grund null zurückgegeben hat
if (!isset($pdo) || $pdo === null) {
    die(
        "❌ Fehler: Die Datenbankverbindung konnte nicht initialisiert werden."
    );
}

// =========================================================================
// AB HIER FOLGT DEIN BESTEHENDER CRUD-CODE (Verarbeitung von POST/GET, usw.)
// =========================================================================

// 2. CRUD-Operationen verarbeiten
$message = "";
$messageType = ""; // success oder error

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : null;
        $text_pack = trim($_POST["text_pack"] ?? "default");
        $text_group = trim($_POST["text_group"] ?? "");
        $content = trim($_POST["content"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if (empty($text_pack) || empty($text_group) || empty($content)) {
            $message =
                "❌ Bitte Paket, Gruppe und Inhalt vollständig ausfüllen.";
            $messageType = "error";
        } else {
            try {
                if ($id) {
                    // UPDATE bestehender Eintrag
                    $stmt = $pdo->prepare(
                        "UPDATE snatch_texts SET text_pack = ?, text_group = ?, content = ?, description = ? WHERE id = ?",
                    );
                    $stmt->execute([
                        $text_pack,
                        $text_group,
                        $content,
                        $description,
                        $id,
                    ]);
                    $message = "✨ Text erfolgreich aktualisiert!";
                    $messageType = "success";
                } else {
                    // INSERT neuer Eintrag
                    $stmt = $pdo->prepare(
                        "INSERT INTO snatch_texts (text_pack, text_group, content, description) VALUES (?, ?, ?, ?)",
                    );
                    $stmt->execute([
                        $text_pack,
                        $text_group,
                        $content,
                        $description,
                    ]);
                    $message = "🎁 Neuer Text erfolgreich hinzugefügt!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "❌ Fehler beim Speichern: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif ($action === "delete") {
        $id = intval($_POST["id"] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM snatch_texts WHERE id = ?");
                $stmt->execute([$id]);
                $message = "🗑️ Eintrag erfolgreich gelöscht.";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "❌ Fehler beim Löschen: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// 3. Suchfilter und Datenabfrage
$search = $_GET["search"] ?? "";
$filter_pack = $_GET["filter_pack"] ?? "";

$queryStr = "SELECT * FROM snatch_texts WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .=
        " AND (content LIKE ? OR text_group LIKE ? OR description LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($filter_pack)) {
    $queryStr .= " AND text_pack = ?";
    $params[] = $filter_pack;
}

$queryStr .= " ORDER BY text_pack ASC, text_group ASC, id DESC";
$stmt = $pdo->prepare($queryStr);
$stmt->execute($params);
$allTexts = $stmt->fetchAll();

// 1. Alle verfügbaren Packs für den Filter & die Datalist holen
$packsStmt = $pdo->query(
    "SELECT DISTINCT text_pack FROM snatch_texts ORDER BY text_pack ASC",
);
$allPacks = $packsStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Alle aktuell genutzten Gruppen aus der DB für die Datalist holen
$groupsStmt = $pdo->query(
    "SELECT DISTINCT text_group FROM snatch_texts ORDER BY text_group ASC",
);
$allUsedGroups = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);

// Falls die DB noch leer ist, vordefinierte Standard-Gruppen als Backup hinzufügen
$standardGroups = [];
foreach ($standardGroups as $sg) {
    if (!in_array($sg, $allUsedGroups)) {
        $allUsedGroups[] = $sg;
    }
}
sort($allUsedGroups);

// Alle verfügbaren Packs für den Filter-Dropdown holen
$packsStmt = $pdo->query(
    "SELECT DISTINCT text_pack FROM snatch_texts ORDER BY text_pack ASC",
);
$allPacks = $packsStmt->fetchAll(PDO::FETCH_COLUMN);

// --- AUTOMATISCHE PLATZHALTER-SUCHE ---
// Standard-Tags vordefinieren, falls die DB am Anfang leer ist
$extractedPlaceholders = [];
foreach ($allTexts as $t) {
    // Regex sucht nach allen Vorkommen von {irgendwas} im Content
    if (preg_match_all("/\{[a-zA-Z0-9_]+\}/", $t["content"], $matches)) {
        foreach ($matches[0] as $match) {
            if (!in_array($match, $extractedPlaceholders)) {
                $extractedPlaceholders[] = $match;
            }
        }
    }
}
sort($extractedPlaceholders);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snatch Text-Verwaltung Dashboard</title>
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #0f172a;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --success: #10b981;
            --error: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 2rem; }
        .container { max-width: 1280px; margin: 0 auto; }

        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 2rem; }
        h1 { font-size: 1.75rem; background: linear-gradient(to right, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 1.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--error); color: #f87171; }

        .layout-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        @media (min-width: 950px) { .layout-grid { grid-template-columns: 420px 1fr; } }

        .card { background-color: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2); height: fit-content; }
        .card h2 { font-size: 1.2rem; margin-bottom: 1.25rem; color: #f1f5f9; }

        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.85rem; color: var(--text-dim); margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"], textarea, select { width: 100%; background-color: var(--bg-input); border: 1px solid var(--border); border-radius: 6px; padding: 10px; color: var(--text-main); font-size: 0.9rem; }
        input[type="text"]:focus, textarea:focus, select:focus { outline: none; border-color: var(--accent); }
        textarea { resize: vertical; min-height: 110px; }

        /* TAG-INJEKTOR CONTAINER */
        .tag-container { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 0.5rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; border: 1px dashed var(--border); }
        .tag { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #93c5fd; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-family: monospace; cursor: pointer; user-select: none; transition: all 0.2s; }
        .tag:hover { background: var(--accent); color: white; transform: translateY(-1px); }

        .btn { display: inline-flex; align-items: center; justify-content: center; background-color: var(--accent); color: white; border: none; padding: 10px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; cursor: pointer; width: 100%; }
        .btn:hover { background-color: var(--accent-hover); }
        .btn-secondary { background-color: transparent; border: 1px solid var(--border); color: var(--text-main); margin-top: 0.5rem; }
        .btn-secondary:hover { background-color: rgba(255,255,255,0.05); }

        .toolbar { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; background: var(--bg-card); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); align-items: flex-end; }
        .toolbar .form-group { margin-bottom: 0; flex: 1; min-width: 200px; }
        .toolbar .btn-search { width: auto; height: 40px; }

        .table-responsive { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; background-color: var(--bg-card); }
        th { background-color: rgba(0,0,0,0.3); padding: 12px 16px; color: var(--text-main); border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: top; }
        tr:hover td { background-color: rgba(255,255,255,0.01); }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-pack { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.4); }
        .badge-group {
            background: rgba(234, 179, 8, 0.2);
            color: #fef08a;
            border: 1px solid rgba(234, 179, 8, 0.4);
            transition: all 0.2s ease;
        }

        /* Neuer Hover-Effekt für die klickbare Gruppe */
        .badge-group:hover {
            background: #eab308;
            color: #0f172a;
            box-shadow: 0 0 8px rgba(234, 179, 8, 0.4);
        }
        .actions-cell { display: flex; gap: 8px; }
        .btn-action { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 4px; border-radius: 4px; }
        .btn-action:hover { background: rgba(255,255,255,0.08); }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-dim); }
        .emoji-picker-container {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            border-radius: 6px;
            border: 1px dashed var(--border);
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 250px; /* Begrenzt die Höhe und aktiviert Scrollen */
            overflow-y: auto;
        }

        .emoji-category {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-dim);
            padding-bottom: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03); /* Dezente Trennlinie zwischen Kategorien */
        }

        .emoji-category:last-child {
            border-bottom: none;
        }

        .emoji-category span:first-child {
            font-weight: 600;
            color: #93c5fd; /* Leicht bläulicher Titel für die Kategorien */
            min-width: 150px;
        }

        .emoji-item {
            font-size: 1.2rem;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: all 0.15s;
            user-select: none;
        }

        .emoji-item:hover {
            background: rgba(59, 130, 246, 0.25);
            color: #fff;
            transform: scale(1.25);
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div>
            <h1>Snatch Text-Verwaltung Dashboard</h1>
            <p style="color: var(--text-dim); font-size: 0.85rem; margin-top: 4px;">Packs & Gruppen Templates mit Kaskaden-Fallback verwalten</p>
        </div>
        <a href="?" style="color: var(--accent); text-decoration: none; font-size: 0.9rem;">🔄 Aktualisieren</a>
    </header>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars(
    $message,
); ?></div>
    <?php endif; ?>

    <div class="layout-grid">

    <datalist id="packSuggestions">
        <?php if (!in_array("default", $allPacks)): ?>
            <option value="default">
        <?php endif; ?>
        <?php foreach ($allPacks as $p): ?>
            <option value="<?php echo htmlspecialchars($p); ?>">
        <?php endforeach; ?>
    </datalist>

    <datalist id="groupSuggestions">
        <?php foreach ($allUsedGroups as $g): ?>
            <option value="<?php echo htmlspecialchars($g); ?>">
        <?php endforeach; ?>
    </datalist>

    <div class="card">
        <h2 id="formTitle">📝 Eintrag hinzufügen</h2>
        <form id="textForm" method="POST" action="">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="">

            <div class="form-group">
                <label for="formPack">Text-Paket (text_pack)</label>
                <input type="text" name="text_pack" id="formPack" value="default" list="packSuggestions" placeholder="z.B. default, halloween, weihnachten" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="formGroup">Rolle / Gruppe (text_group)</label>
                <input type="text" name="text_group" id="formGroup" list="groupSuggestions" placeholder="z.B. winner_champion, loser_fail" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="formContent">Inhalt (Mit Cursor-Injektion)</label>
                <textarea name="content" id="formContent" placeholder="Füge hier deinen Text ein..." required></textarea>

                <label style="margin-top: 0.6rem; margin-bottom: 2px;">Gefundene System-Tags (Klicken):</label>
                <div class="tag-container">
                    <?php foreach ($extractedPlaceholders as $placeholder): ?>
                        <span class="tag" onclick="insertText('<?php echo $placeholder; ?>')"><?php echo htmlspecialchars(
    $placeholder,
); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label style="margin-bottom: 2px;">Fantasy & RPG Emojis (Klicken):</label>
                </div>




                    <label style="margin-top: 0.8rem; margin-bottom: 2px;">Fantasy & RPG Emoji-Auswahl (Klicken zum Einfügen):</label>
                    <div class="emoji-picker-container">
                        <div class="emoji-category">
                            <span>⚔️ Kampf & Rüstung:</span>
                            <span class="emoji-item" onclick="insertText('⚔️')">⚔️</span>
                            <span class="emoji-item" onclick="insertText('🗡️')">🗡️</span>
                            <span class="emoji-item" onclick="insertText('🪓')">🪓</span>
                            <span class="emoji-item" onclick="insertText('🛡️')">🛡️</span>
                            <span class="emoji-item" onclick="insertText('🏹')">🏹</span>
                            <span class="emoji-item" onclick="insertText('⛓️')">⛓️</span>
                            <span class="emoji-item" onclick="insertText('🦹')">🦹</span>
                            <span class="emoji-item" onclick="insertText('🤺')">🤺</span>
                            <span class="emoji-item" onclick="insertText('🔱')">🔱</span>
                        </div>
                        <div class="emoji-category">
                            <span>🔮 Magie & Alchemie:</span>
                            <span class="emoji-item" onclick="insertText('🔮')">🔮</span>
                            <span class="emoji-item" onclick="insertText('🪄')">🪄</span>
                            <span class="emoji-item" onclick="insertText('📜')">📜</span>
                            <span class="emoji-item" onclick="insertText('📖')">📖</span>
                            <span class="emoji-item" onclick="insertText('🧪')">🧪</span>
                            <span class="emoji-item" onclick="insertText('🏺')">🏺</span>
                            <span class="emoji-item" onclick="insertText('✨')">✨</span>
                            <span class="emoji-item" onclick="insertText('🌟')">🌟</span>
                            <span class="emoji-item" onclick="insertText('☀️')">☀️</span>
                            <span class="emoji-item" onclick="insertText('🌙')">🌙</span>
                            <span class="emoji-item" onclick="insertText('🕯️')">🕯️</span>
                        </div>
                        <div class="emoji-category">
                            <span>🐉 Kreaturen & Monster:</span>
                            <span class="emoji-item" onclick="insertText('🐉')">🐉</span>
                            <span class="emoji-item" onclick="insertText('🐲')">🐲</span>
                            <span class="emoji-item" onclick="insertText('🐺')">🐺</span>
                            <span class="emoji-item" onclick="insertText('🦇')">🦇</span>
                            <span class="emoji-item" onclick="insertText('🦉')">🦉</span>
                            <span class="emoji-item" onclick="insertText('🕷️')">🕷️</span>
                            <span class="emoji-item" onclick="insertText('🦂')">🦂</span>
                            <span class="emoji-item" onclick="insertText('💀')">💀</span>
                            <span class="emoji-item" onclick="insertText('☠️')">☠️</span>
                            <span class="emoji-item" onclick="insertText('👻')">👻</span>
                            <span class="emoji-item" onclick="insertText('👹')">👹</span>
                        </div>
                        <div class="emoji-category">
                            <span>🏰 Abenteuer & Orte:</span>
                            <span class="emoji-item" onclick="insertText('🏰')">🏰</span>
                            <span class="emoji-item" onclick="insertText('🏛️')">🏛️</span>
                            <span class="emoji-item" onclick="insertText('🛖')">🛖</span>
                            <span class="emoji-item" onclick="insertText('🗺️')">🗺️</span>
                            <span class="emoji-item" onclick="insertText('⛺')">⛺</span>
                            <span class="emoji-item" onclick="insertText('🌲')">🌲</span>
                            <span class="emoji-item" onclick="insertText('⛰️')">⛰️</span>
                            <span class="emoji-item" onclick="insertText('🌋')">🌋</span>
                            <span class="emoji-item" onclick="insertText('🧱')">🧱</span>
                            <span class="emoji-item" onclick="insertText('🗝️')">🗝️</span>
                            <span class="emoji-item" onclick="insertText('🔑')">🔑</span>
                        </div>
                        <div class="emoji-category">
                            <span>💎 Loot & Reichtum:</span>
                            <span class="emoji-item" onclick="insertText('👑')">👑</span>
                            <span class="emoji-item" onclick="insertText('💎')">💎</span>
                            <span class="emoji-item" onclick="insertText('💰')">💰</span>
                            <span class="emoji-item" onclick="insertText('🪙')">🪙</span>
                            <span class="emoji-item" onclick="insertText('🏆')">🏆</span>
                            <span class="emoji-item" onclick="insertText('📦')">📦</span>
                            <span class="emoji-item" onclick="insertText('🧳')">🧳</span>
                            <span class="emoji-item" onclick="insertText('💍')">💍</span>
                            <span class="emoji-item" onclick="insertText('🎲')">🎲</span>
                            <span class="emoji-item" onclick="insertText('🃏')">🃏</span>
                        </div>
                        <div class="emoji-category">
                            <span>🍖 Taverne & Proviant:</span>
                            <span class="emoji-item" onclick="insertText('🍺')">🍺</span>
                            <span class="emoji-item" onclick="insertText('🍷')">🍷</span>
                            <span class="emoji-item" onclick="insertText('🥛')">🥛</span>
                            <span class="emoji-item" onclick="insertText('🍖')">🍖</span>
                            <span class="emoji-item" onclick="insertText('🍗')">🍗</span>
                            <span class="emoji-item" onclick="insertText('🍞')">🍞</span>
                            <span class="emoji-item" onclick="insertText('🧀')">🧀</span>
                            <span class="emoji-item" onclick="insertText('🍏')">🍏</span>
                            <span class="emoji-item" onclick="insertText('🍄')">🍄</span>
                            <span class="emoji-item" onclick="insertText('🔥')">🔥</span>
                        </div>
                        <div class="emoji-category">
                            <span>📉 Status-Effekte:</span>
                            <span class="emoji-item" onclick="insertText('🎉')">🎉</span> <span class="emoji-item" onclick="insertText('💔')">💔</span> <span class="emoji-item" onclick="insertText('🧼')">🧼</span> <span class="emoji-item" onclick="insertText('📈')">📈</span> <span class="emoji-item" onclick="insertText('📉')">📉</span> <span class="emoji-item" onclick="insertText('🚨')">🚨</span> <span class="emoji-item" onclick="insertText('⚠️')">⚠️</span> <span class="emoji-item" onclick="insertText('❌')">❌</span> <span class="emoji-item" onclick="insertText('🤝')">🤝</span> <span class="emoji-item" onclick="insertText('🕳️')">🕳️</span> </div>
                    </div>

                <div class="form-group">
                    <label for="description">Beschreibung / interne Notiz (Optional)</label>
                    <input type="text" name="description" id="formDescription" placeholder="z.B. Erwartet: {winnerPing}, {winnerPoints}">
                </div>

                <button type="submit" class="btn" id="btnSubmit">Eintrag speichern</button>
                <button type="button" class="btn btn-secondary" id="btnReset" style="display: none;" onclick="resetForm()">Bearbeiten abbrechen</button>
            </form>
        </div>

        <div>
            <form method="GET" action="" class="toolbar">
                <div class="form-group">
                    <label>Suche nach Begriffen</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars(
                        $search,
                    ); ?>" placeholder="Inhalt, Gruppe, Notiz...">
                </div>
                <div class="form-group">
                    <label>Filter nach Paket</label>
                    <select name="filter_pack">
                        <option value="">-- Alle Pakete --</option>
                        <?php foreach ($allPacks as $p): ?>
                            <option value="<?php echo htmlspecialchars(
                                $p,
                            ); ?>" <?php echo $filter_pack === $p
    ? "selected"
    : ""; ?>><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-search">🔍 Filtern</button>
                <?php if (!empty($search) || !empty($filter_pack)): ?>
                    <a href="?" class="btn btn-secondary btn-search" style="text-decoration:none; margin:0; line-height:38px;">✖ Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Paket / Gruppe</th>
                            <th>Inhalt</th>
                            <th>Notiz</th>
                            <th style="width: 80px; text-align: center;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allTexts)): ?>
                            <tr><td colspan="4" class="empty-state">🕳️ Keine passenden Einträge in der Tabelle gefunden.</td></tr>
                        <?php else: ?>
                        <?php foreach ($allTexts as $row): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-pack" title="Text-Paket"><?php echo htmlspecialchars(
                                        $row["text_pack"] ?? "default",
                                    ); ?></span>

                                    <div style="margin-top: 6px;">
                                        <span class="badge badge-group"
                                              onclick="copyPhpFunction('<?php echo htmlspecialchars(
                                                  $row["text_group"],
                                              ); ?>', <?php echo $row[
    "id"
]; ?>)"
                                              title="Klicken, um fertigen PHP-Code zu kopieren!"
                                              style="cursor: pointer; user-select: none;">
                                            📋 <?php echo htmlspecialchars(
                                                $row["text_group"] ?? "",
                                            ); ?>
                                        </span>
                                    </div>
                                </td>

                                <td style="white-space: pre-wrap; font-weight: 500; color: #e2e8f0;" id="content-<?php echo $row[
                                    "id"
                                ]; ?>"><?php echo htmlspecialchars(
    $row["content"],
); ?></td>

                                <td style="color: var(--text-dim); font-size: 0.8rem;"><?php echo htmlspecialchars(
                                    $row["description"] ?? "",
                                ); ?></td>

                                <td class="actions-cell">
                                    <button class="btn-action" title="Bearbeiten" onclick="editRow(<?php echo htmlspecialchars(
                                        json_encode($row),
                                    ); ?>)">✏️</button>
                                    <form method="POST" action="" onsubmit="return confirm('Eintrag wirklich löschen?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $row[
                                            "id"
                                        ]; ?>">
                                        <button type="submit" class="btn-action" style="color: var(--error);">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Universelle Funktion, um Text oder Emojis an der Cursor-Position einzufügen
function insertText(str) {
    const textarea = document.getElementById('formContent');
    const startPos = textarea.selectionStart;
    const endPos = textarea.selectionEnd;
    const text = textarea.value;

    // Text/Emoji exakt an der Cursor-Position einsetzen
    textarea.value = text.substring(0, startPos) + str + text.substring(endPos, text.length);

    // Cursor direkt hinter dem eingefügten Element platzieren
    textarea.selectionStart = textarea.selectionEnd = startPos + str.length;

    // Textarea wieder fokussieren
    textarea.focus();
}

// Zeile in das Bearbeitungs-Formular laden
function editRow(data) {
    document.getElementById('formTitle').innerText = "✏️ Eintrag bearbeiten (ID: " + data.id + ")";
    document.getElementById('formId').value = data.id;
    document.getElementById('formPack').value = data.text_pack;
    document.getElementById('formGroup').value = data.text_group;
    document.getElementById('formContent').value = data.content;
    document.getElementById('formDescription').value = data.description;

    document.getElementById('btnSubmit').innerText = "Änderungen speichern";
    document.getElementById('btnReset').style.display = "inline-flex";
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Editier-Modus abbrechen / Formular leeren
function resetForm() {
    document.getElementById('formTitle').innerText = "📝 Eintrag hinzufügen";
    document.getElementById('formId').value = "";
    document.getElementById('formPack').value = "default";
    document.getElementById('formGroup').value = "";
    document.getElementById('formContent').value = "";
    document.getElementById('formDescription').value = "";

    document.getElementById('btnSubmit').innerText = "Eintrag speichern";
    document.getElementById('btnReset').style.display = "none";
}




function copyPhpFunction(groupName, textId) {
    // 1. Den Textinhalt aus der Tabellenzelle holen
    const textElement = document.getElementById('content-' + textId);
    if (!textElement) return;
    const content = textElement.innerText;

    // 2. Regex verwenden, um alle {platzhalter} zu finden
    const regex = /\{([a-zA-Z0-9_]+)\}/g;
    let matches;
    let replacements = [];

    // Alle Platzhalter sammeln und als PHP-Array-Syntax formatieren
    while ((matches = regex.exec(content)) !== null) {
        const placeholder = matches[0]; // z.B. '{winnerPing}'
        // Verhindert doppelte Einträge, falls ein Tag mehrfach im Text vorkommt
        if (!replacements.includes(placeholder)) {
            replacements.push(`        '${placeholder}' => $${matches[1]}`);
        }
    }

    // 3. Den PHP-String zusammensetzen
    let phpCode = `getRandomSnatchText($pdo, $activePack, '${groupName}'`;

    if (replacements.length > 0) {
        phpCode += `, [\n${replacements.join(",\n")}\n]);`;
    } else {
        phpCode += `);`; // Keine Platzhalter vorhanden
    }

    // 4. In die Zwischenablage kopieren
    navigator.clipboard.writeText(phpCode).then(() => {
        showToast(`📋 PHP-Code für '${groupName}' kopiert!`);
    }).catch(err => {
        console.error('Fehler beim Kopieren: ', err);
    });
}

// Hilfsfunktion für eine edle Toast-Benachrichtigung
function showToast(message) {
    let toast = document.getElementById('copy-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'copy-toast';
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.background = 'var(--success)';
        toast.style.color = '#fff';
        toast.style.padding = '12px 20px';
        toast.style.borderRadius = '6px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
        toast.style.zIndex = '10000';
        toast.style.fontWeight = '600';
        toast.style.fontSize = '0.9rem';
        toast.style.transition = 'opacity 0.3s';
        document.body.appendChild(toast);
    }
    toast.innerText = message;
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.opacity = '0';
    }, 2500);
}
</script>
</body>
</html>
