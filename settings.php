<?php
// Session starten für den Login-Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOGIN CHECK ---
$is_logged_in = isset($_SESSION["loggedin"]);

// Wenn nicht eingeloggt: Zugriff verweigern
if (!$is_logged_in) { ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Zugriff verweigert</title>
        <style>
            body { background-color: #0f172a; color: #f8fafc; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .lock-card { background: #1e293b; padding: 30px; border-radius: 8px; border: 1px solid #475569; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
            h2 { color: #ef4444; margin-top: 0; }
        </style>
    </head>
    <body>
        <div class="lock-card">
            <h2>🔒 Zugriff verweigert</h2>
            <p>Du musst eingeloggt sein, um diese Seite aufzurufen.</p>
        </div>
    </body>
    </html>
    <?php exit();}

// --- DATABASE CONNECTION AUS EXTERNER DATEI ---
require_once "db.php";
$pdo = getDatabaseConnection();

$message = "";

// --- ACTION HANDLING (POST) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Eintrag hinzufügen
    if (isset($_POST["action"]) && $_POST["action"] === "add") {
        $serverId = trim($_POST["server_id"] ?? "");
        $settingKey = trim($_POST["setting_key"] ?? "");
        $settingValue = trim($_POST["setting_value"] ?? "");
        $info = trim($_POST["info"] ?? ""); // NEU: Info auslesen

        if ($serverId !== "" && $settingKey !== "" && $settingValue !== "") {
            // NEU: info in INSERT hinzugefügt
            $stmt = $pdo->prepare(
                "INSERT INTO snatch_settings (server_id, setting_key, setting_value, info) VALUES (?, ?, ?, ?)",
            );
            $stmt->execute([$serverId, $settingKey, $settingValue, $info]);
            $message =
                "<div class='alert success'>Eintrag erfolgreich hinzugefügt!</div>";
        } else {
            $message =
                "<div class='alert error'>Bitte alle Pflichtfelder ausfüllen.</div>";
        }
    }

    // 2. Eintrag aktualisieren
    if (isset($_POST["action"]) && $_POST["action"] === "update") {
        $serverId = trim($_POST["server_id"] ?? "");
        $settingKey = trim($_POST["setting_key"] ?? "");
        $settingValue = trim($_POST["setting_value"] ?? "");
        $info = trim($_POST["info"] ?? ""); // NEU: Info auslesen

        // Die alten Werte, um den Eintrag eindeutig zu finden
        $oldServerId = trim($_POST["old_server_id"] ?? "");
        $oldSettingKey = trim($_POST["old_setting_key"] ?? "");

        if (
            $serverId !== "" &&
            $settingKey !== "" &&
            $oldServerId !== "" &&
            $oldSettingKey !== ""
        ) {
            // NEU: info in UPDATE hinzugefügt
            $stmt = $pdo->prepare(
                "UPDATE snatch_settings SET server_id = ?, setting_key = ?, setting_value = ?, info = ? WHERE server_id = ? AND setting_key = ?",
            );
            $stmt->execute([
                $serverId,
                $settingKey,
                $settingValue,
                $info,
                $oldServerId,
                $oldSettingKey,
            ]);
            $message =
                "<div class='alert success'>Eintrag erfolgreich aktualisiert!</div>";
        }
    }

    // 3. Eintrag löschen
    if (isset($_POST["action"]) && $_POST["action"] === "delete") {
        $serverId = trim($_POST["server_id"] ?? "");
        $settingKey = trim($_POST["setting_key"] ?? "");

        if ($serverId !== "" && $settingKey !== "") {
            $stmt = $pdo->prepare(
                "DELETE FROM snatch_settings WHERE server_id = ? AND setting_key = ?",
            );
            $stmt->execute([$serverId, $settingKey]);
            $message = "<div class='alert success'>Eintrag gelöscht.</div>";
        }
    }
}

// --- ENTRIES FETCH (Sortiert nach setting_key) ---
$stmt = $pdo->query(
    "SELECT * FROM snatch_settings ORDER BY setting_key ASC, server_id ASC",
);
$settings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snatch Settings Admin</title>
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --border: #475569;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #38bdf8;
            --accent-hover: #0ea5e9;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --success: #22c55e;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 1200px; /* Leicht erhöht, da eine Spalte dazugekommen ist */
        }

        h1, h2 {
            color: var(--accent);
            margin-top: 0;
        }

        h1 { border-bottom: 2px solid var(--border); padding-bottom: 10px; margin-bottom: 30px;}

        /* Formular & Cards */
        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .form-inline {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 200px;
        }

        label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: bold;
        }

        input, select {
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 10px;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.95rem;
        }

        .btn-primary { background-color: var(--accent); color: var(--bg-main); }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-danger:hover { background-color: var(--danger-hover); }

        /* Tabelle */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .table-input {
            width: 100%;
            box-sizing: border-box;
            padding: 6px 10px;
            font-size: 0.9rem;
        }

        /* Alerts */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }
        .alert.success { background-color: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid var(--success); }
        .alert.error { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid var(--danger); }

        .action-cell {
            display: flex;
            gap: 8px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>⚙️ Snatch Settings Control Panel</h1>

    <?= $message ?>

    <div class="card">
        <h2>➕ Neuen Parameter hinzufügen</h2>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label for="setting_key">Setting Key</label>
                <input type="text" id="setting_key" name="setting_key" placeholder="z.B. secret_first_pull" required>
            </div>
            <div class="form-group">
                <label for="server_id">Server ID (0 = Global Default)</label>
                <input type="text" id="server_id" name="server_id" placeholder="z.B. 9638576473..." required>
            </div>
            <div class="form-group">
                <label for="setting_value">Setting Value</label>
                <input type="text" id="setting_value" name="setting_value" placeholder="z.B. 1 oder gold" required>
            </div>

            <!-- NEU: Info-Feld im Hinzufügen-Formular -->
            <div class="form-group" style="flex: 1.5;">
                <label for="info">Beschreibung / Info</label>
                <input type="text" id="info" name="info" placeholder="Wofür ist dieser Key da?">
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>

    <div class="card">
        <h2>📋 Bestehende Konfigurationen (Sortiert nach Key)</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Setting Key</th>
                    <th style="width: 20%;">Server ID</th>
                    <th style="width: 20%;">Setting Value</th>
                    <th style="width: 25%;">Info / Beschreibung</th> <!-- NEU -->
                    <th style="width: 15%;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($settings)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); font-style: italic;">Keine Einträge in der Datenbank gefunden.</td>
                    </tr>
                <?php // NEU
                    // NEU
                    // NEU
                    // NEU
                    else: ?>
                    <?php foreach ($settings as $row): ?>
                        <?php
                        $valKey = htmlspecialchars(
                            $row["setting_key"] ?? "",
                            ENT_QUOTES,
                            "UTF-8",
                        );
                        $valServer = htmlspecialchars(
                            $row["server_id"] ?? "",
                            ENT_QUOTES,
                            "UTF-8",
                        );
                        $valValue = htmlspecialchars(
                            $row["setting_value"] ?? "",
                            ENT_QUOTES,
                            "UTF-8",
                        );
                        $valInfo = htmlspecialchars(
                            $row["info"] ?? "",
                            ENT_QUOTES,
                            "UTF-8",
                        );

                        $formId = "form-update-" . md5($valKey . $valServer);
                        ?>
                        <tr>
                            <td>
                                <input type="text" name="setting_key" value="<?= $valKey ?>" class="table-input" form="<?= $formId ?>" required>
                            </td>

                            <td>
                                <input type="text" name="server_id" value="<?= $valServer ?>" class="table-input" form="<?= $formId ?>" required>
                            </td>

                            <td>
                                <input type="text" name="setting_value" value="<?= $valValue ?>" class="table-input" form="<?= $formId ?>" required>
                            </td>

                            <!-- NEU: Info-Zelle in der Tabellenzeile -->
                            <td>
                                <input type="text" name="info" value="<?= $valInfo ?>" class="table-input" form="<?= $formId ?>" placeholder="Keine Info">
                            </td>

                            <td>
                                <div class="action-cell">
                                    <form id="<?= $formId ?>" method="POST" style="display:none;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="old_setting_key" value="<?= $valKey ?>">
                                        <input type="hidden" name="old_server_id" value="<?= $valServer ?>">
                                    </form>

                                    <button type="submit" form="<?= $formId ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem;" title="Speichern">💾</button>

                                    <form method="POST" onsubmit="return confirm('Eintrag wirklich löschen?');" style="margin:0; display:inline-block;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="setting_key" value="<?= $valKey ?>">
                                        <input type="hidden" name="server_id" value="<?= $valServer ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85rem;" title="Löschen">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
