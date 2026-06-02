<?php
// Session starten, falls das nicht schon global oder in der db.php passiert
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sicherheits-Prüfung: Wenn nicht eingeloggt, bleibt die Seite komplett leer
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit();
}

require_once "db.php";
$pdo = getDatabaseConnection();

$msg = "";

// Speichern-Logik
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Logik für die Karten-Pools
    if (isset($_POST["update_pool"])) {
        $id = $_POST["cat_id"];
        $lines = explode("\n", $_POST["name_pool"]);
        $cleanList = array_values(array_filter(array_map("trim", $lines)));
        $newPool = json_encode($cleanList, JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare(
            "UPDATE snatch_game_card_types SET name_pool = ? WHERE id = ?",
        );
        $stmt->execute([$newPool, $id]);
        $msg =
            "⚔️ Der Namens-Pool für " .
            htmlspecialchars($_POST["cat_name"]) .
            " wurde neu gewoben.";
    }
    // 2. Logik für die Zusatznamen
    if (isset($_POST["update_zusatz"])) {
        $lines = explode("\n", $_POST["zusatz_pool"]);
        $cleanList = array_values(array_filter(array_map("trim", $lines)));

        try {
            $pdo->exec("TRUNCATE TABLE snatch_kartenzusatz");
            if (!empty($cleanList)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO snatch_kartenzusatz (zusatzname) VALUES (?)",
                );
                foreach ($cleanList as $zusatz) {
                    $stmt->execute([$zusatz]);
                }
            }
            $msg =
                "✨ " .
                count($cleanList) .
                " magische Zusätze im Archiv versiegelt.";
        } catch (Exception $e) {
            $msg = "💥 Ein Fehler störte das Ritual: " . $e->getMessage();
        }
    }
}

// Daten aus der Datenbank laden
$categories = $pdo
    ->query("SELECT * FROM snatch_game_card_types ORDER BY start_id ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

// Alle Zusatznamen holen und für die Textarea vorbereiten
$zusatzeArr = $pdo
    ->query("SELECT zusatzname FROM snatch_kartenzusatz ORDER BY id ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
$zusatzeText = implode("\n", $zusatzeArr);
$anzahlZusaetze = count($zusatzeArr);

// Anzahl der Zusätze ermitteln
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sanktum der Weltenweber</title>
    <style>
        body { background: #121212; color: #e0e0e0; font-family: 'Georgia', serif; padding: 20px; }
        .box { background: #1e1e1e; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #3a2f1d; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h1 { color: #d35400; border-bottom: 2px solid #3a2f1d; padding-bottom: 10px; font-weight: normal; }
        h2 { color: #e67e22; font-weight: normal; margin-top: 0; }
        select { background: #2a2a2a; color: #fff; border: 1px solid #555; padding: 10px; border-radius: 4px; font-size: 16px; margin-bottom: 20px; width: 100%; max-width: 450px; font-family: sans-serif; }
        textarea { width: 100%; height: 350px; background: #151515; color: #2ecc71; border: 1px solid #444; padding: 10px; font-family: monospace; font-size: 14px; line-height: 1.5; box-sizing: border-box; }
        button { cursor: pointer; padding: 12px 20px; border-radius: 4px; border: none; font-weight: bold; margin-top: 15px; font-size: 14px; background: #d35400; color: white; transition: 0.2s; }
        button:hover { background: #e67e22; }
        .alert { background: #27ae60; color: white; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; border-left: 5px solid #1e8449; }
        .hidden { display: none; }
        .count-badge { color: #888; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>📜 Das Sanktum der Weltenweber: Karten-Archive</h1>

    <?php if (!empty($msg)) {
        echo "<div class='alert'>$msg</div>";
    } ?>

    <!-- Hauptauswahl -->
    <div class="box">
        <label style="display:block; margin-bottom: 8px; font-weight: bold;">Welches Archiv soll eingesehen werden?</label>
        <select id="mainTableSelector" onchange="toggleMainSection()">
            <option value="kategorien">Karten-Kategorien (Namen-Pools)</option>
            <option value="zusatz">Zusatznamen (Aktuell: <?php echo $anzahlZusaetze; ?> Einträge)</option>
        </select>

        <!-- Unterauswahl für Kategorien -->
        <div id="subCategoryWrapper">
            <label style="display:block; margin-bottom: 8px; font-weight: bold;">Kategorie wählen:</label>
            <select id="catSelect" onchange="showCatPool()">
                <?php foreach ($categories as $c) {
                    // Anzahl der Einträge im JSON-Array direkt berechnen
                    $poolArray = json_decode($c["name_pool"] ?? "[]", true);
                    $anzahlEintraege = is_array($poolArray)
                        ? count($poolArray)
                        : 0;

                    echo "<option value='{$c["id"]}'>{$c["emoji"]} {$c["name"]} ({$anzahlEintraege} Namen)</option>";
                } ?>
            </select>
        </div>
    </div>

    <!-- SEKTION 1: KARTEN-KATEGORIEN POOLS -->
    <div id="section_kategorien" class="main-section">
        <?php foreach ($categories as $c): ?>
            <div id="cat_<?php echo $c[
                "id"
            ]; ?>" class="box cat-div" style="display:none;">
                <h2>Essenz-Pool für: <?php echo $c["emoji"] .
                    " " .
                    $c["name"]; ?></h2>
                <p style="color: #888; font-size: 0.9em;">Füge pro Zeile einen Namen hinzu oder entferne ihn.</p>
                <form method="POST">
                    <input type="hidden" name="cat_id" value="<?php echo $c[
                        "id"
                    ]; ?>">
                    <input type="hidden" name="cat_name" value="<?php echo $c[
                        "name"
                    ]; ?>">
                    <textarea name="name_pool"><?php echo implode(
                        "\n",
                        json_decode($c["name_pool"] ?? "[]", true),
                    ); ?></textarea>
                    <br>
                    <button type="submit" name="update_pool">Archiv versiegeln</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SEKTION 2: ZUSATZNAMEN -->
    <div id="section_zusatz" class="main-section hidden">
        <div class="box">
            <h2>✨ Liste der Kartenzusätze</h2>
            <p style="color: #888; font-size: 0.9em;">Trage jeden Zusatz in eine eigene Zeile ein. Die IDs werden beim Speichern automatisch neu geschmiedet.</p>
            <form method="POST">
                <textarea name="zusatz_pool"><?php echo htmlspecialchars(
                    $zusatzeText,
                ); ?></textarea>
                <br>
                <button type="submit" name="update_zusatz">Zusätze einweben</button>
            </form>
        </div>
    </div>

    <script>
        function toggleMainSection() {
            const mode = document.getElementById('mainTableSelector').value;
            if (mode === 'kategorien') {
                document.getElementById('section_kategorien').classList.remove('hidden');
                document.getElementById('subCategoryWrapper').classList.remove('hidden');
                document.getElementById('section_zusatz').classList.add('hidden');
                showCatPool();
            } else {
                document.getElementById('section_kategorien').classList.add('hidden');
                document.getElementById('subCategoryWrapper').classList.add('hidden');
                document.getElementById('section_zusatz').classList.remove('hidden');
            }
        }

        function showCatPool() {
            if (document.getElementById('mainTableSelector').value !== 'kategorien') return;
            document.querySelectorAll('.cat-div').forEach(el => el.style.display = 'none');
            const currentCat = document.getElementById('catSelect').value;
            const targetDiv = document.getElementById('cat_' + currentCat);
            if (targetDiv) {
                targetDiv.style.display = 'block';
            }
        }

        toggleMainSection();
    </script>
</body>
</html>
