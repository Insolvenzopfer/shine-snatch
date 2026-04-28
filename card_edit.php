<?php
session_start();

// HIER DEIN PASSWORT ÄNDERN zeile auskommmentieren, seite aufrufen und in der variable darunter setzen
//echo password_hash('ShineSnatchIstSuper69!', PASSWORD_DEFAULT);

$admin_password_hash = '$2y$12$peuzsRVkOW/Q6V55HBghK.N.SYmJDb47yHULU9KEC9C0Un1rY3f9S'; 

// Logout-Logik
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: card_edit.php");
    exit;
}

// Login-Prüfung
if (isset($_POST['login_pass'])) {
    if (password_verify($_POST['login_pass'], $admin_password_hash)) {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Falsches Passwort!";
    }
}

// Wenn nicht eingeloggt, Login-Formular anzeigen und Script stoppen
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true): ?>
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
        .error { color: #cf6679; margin-bottom: 10px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔒 Card Editor Login</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="password" name="login_pass" placeholder="Passwort eingeben" autofocus><br>
            <button type="submit">Einloggen</button>
        </form>
    </div>
</body>
</html>
<?php 
exit; // Stoppt das restliche Script, wenn nicht eingeloggt
endif; 

// Ab hier folgt dein normaler Code von card_edit.php...


$file = 'game_data.json';
if (!file_exists($file)) {
    file_put_contents($file, json_encode(['cardTypes' => [], 'groups' => [], 'combos' => []]));
}
$data = json_decode(file_get_contents($file), true);
$message = "";

if (isset($_POST['save'])) {
    $updatedData = ['cardTypes' => [], 'groups' => [], 'combos' => []];

    if (isset($_POST['cards'])) {
        foreach ($_POST['cards'] as $c) {
            if(empty($c['id'])) continue;
            $updatedData['cardTypes'][] = [
                'id' => $c['id'], 'name' => $c['name'], 'count' => (int)$c['count'],
                'points' => (int)$c['points'], 'startId' => (int)$c['startId'], 'emoji' => $c['emoji']
            ];
        }
    }

    if (isset($_POST['groups'])) {
        foreach ($_POST['groups'] as $g) {
            if(empty($g['id'])) continue;
            $updatedData['groups'][] = [
                'id' => $g['id'],
                'cards' => array_filter(array_map('trim', explode(',', $g['cards'])))
            ];
        }
    }

    if (isset($_POST['combos'])) {
        foreach ($_POST['combos'] as $cb) {
            if(empty($cb['name'])) continue;
            $updatedData['combos'][] = [
                'emoji' => $cb['emoji'], 'name' => $cb['name'], 'points' => (int)$cb['points'],
                'needs' => array_map('trim', explode(',', $cb['needs'])), 'cat' => $cb['cat']
            ];
        }
    }

    file_put_contents($file, json_encode($updatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $data = $updatedData;
    $message = "✅ Datenbank erfolgreich aktualisiert!";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Snatch Database Editor Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Signika:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050705; --panel: rgba(20, 25, 20, 0.7); --primary: #2cb24c;
            --accent: #a855f7; --text: #e2e8f0; --danger: #ef4444;
        }

        body { font-family: 'Signika', sans-serif; background: var(--bg); color: var(--text); padding: 40px 20px; margin-bottom: 100px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: var(--panel); backdrop-filter: blur(10px); border-radius: 20px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05); }
        
        h2 { color: var(--accent); display: flex; justify-content: space-between; align-items: center; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        input { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 10px; border-radius: 8px; width: 100%; box-sizing: border-box; }
        input:focus { border-color: var(--primary); outline: none; }

        /* Verstecke Nummer-Pfeile */
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        .btn-add { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 10px; cursor: pointer; font-size: 0.9rem; }
        .btn-del { background: rgba(239, 68, 68, 0.2); color: var(--danger); border: 1px solid var(--danger); padding: 8px; border-radius: 8px; cursor: pointer; width: 35px; }
        .btn-del:hover { background: var(--danger); color: white; }

        .save-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; border: none; padding: 15px 50px; border-radius: 50px; font-weight: 600; cursor: pointer; box-shadow: 0 10px 40px rgba(44, 178, 76, 0.4); z-index: 100; }
        .msg { background: rgba(44, 178, 76, 0.2); color: #fff; padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; border: 1px solid var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <h1>SNATCH DATABASE CONTROL</h1>
    <?php if($message) echo "<div class='msg'>$message</div>"; ?>

    <form method="POST" id="dbForm">
        <div class="section">
            <div style="text-align: right;">
    <a href="?logout=1" style="color: #cf6679; text-decoration: none; font-size: 0.8em;">✖ Editor sperren / Logout</a>
</div>
            <h2>Karten-Typen <button type="button" class="btn-add" onclick="addRow('cardsTable')">+ Neu</button></h2>
            <table id="cardsTable">
                <thead><tr><th>ID</th><th>Emoji</th><th>Name</th><th>Anzahl</th><th>Punkte</th><th>StartId</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['cardTypes'] as $idx => $ct): ?>
                    <tr>
                        <td><input type="text" name="cards[<?=$idx?>][id]" value="<?=$ct['id']?>" style="width:70px; text-align:center; color:var(--primary)"></td>
                        <td><input type="text" name="cards[<?=$idx?>][emoji]" value="<?=$ct['emoji']?>" style="width:50px; text-align:center"></td>
                        <td><input type="text" name="cards[<?=$idx?>][name]" value="<?=$ct['name']?>"></td>
                        <td><input type="number" name="cards[<?=$idx?>][count]" value="<?=$ct['count']?>" style="width:60px; text-align:center"></td>
                        <td><input type="number" name="cards[<?=$idx?>][points]" value="<?=$ct['points']?>" style="width:60px; text-align:center"></td>
                        <td><input type="number" name="cards[<?=$idx?>][startId]" value="<?=$ct['startId']?>" style="width:60px; text-align:center"></td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Gruppen & Aliase <button type="button" class="btn-add" onclick="addRow('groupsTable')">+ Neu</button></h2>
            <table id="groupsTable">
                <thead><tr><th width="150">Gruppen-ID</th><th>Karten-IDs (KRG,MAG...)</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['groups'] as $idx => $g): ?>
                    <tr>
                        <td><input type="text" name="groups[<?=$idx?>][id]" value="<?=$g['id']?>" style="color:var(--accent)"></td>
                        <td><input type="text" name="groups[<?=$idx?>][cards]" value="<?=implode(',', $g['cards'])?>"></td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Kombinationen <button type="button" class="btn-add" onclick="addRow('combosTable')">+ Neu</button></h2>
            <table id="combosTable">
                <thead><tr><th>Emoji</th><th>Name</th><th>Punkte</th><th>Bedarf</th><th>Kat (Kombo-Theme)</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['combos'] as $idx => $cb): ?>
                    <tr>
                        <td><input type="text" name="combos[<?=$idx?>][emoji]" value="<?=$cb['emoji']?>" style="width:50px; text-align:center"></td>
                        <td><input type="text" name="combos[<?=$idx?>][name]" value="<?=$cb['name']?>"></td>
                        <td><input type="number" name="combos[<?=$idx?>][points]" value="<?=$cb['points']?>" style="width:60px; text-align:center"></td>
                        <td><input type="text" name="combos[<?=$idx?>][needs]" value="<?=implode(',', $cb['needs'])?>"></td>
                        <td><input type="text" name="combos[<?=$idx?>][cat]" value="<?=$cb['cat']?>"></td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" name="save" class="save-bar">ÄNDERUNGEN SPEICHERN</button>
    </form>
</div>

<script>
function addRow(tableId) {
    const table = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const newIdx = table.rows.length + Date.now(); // Sicherer Zeitstempel als Index
    let newRow = '';

    if (tableId === 'cardsTable') {
        newRow = `<tr>
            <td><input type="text" name="cards[${newIdx}][id]" placeholder="ID" style="width:70px; text-align:center; color:var(--primary)"></td>
            <td><input type="text" name="cards[${newIdx}][emoji]" placeholder="✨" style="width:50px; text-align:center"></td>
            <td><input type="text" name="cards[${newIdx}][name]" placeholder="Karten-Name"></td>
            <td><input type="number" name="cards[${newIdx}][count]" value="0" style="width:60px; text-align:center"></td>
            <td><input type="number" name="cards[${newIdx}][points]" value="0" style="width:60px; text-align:center"></td>
            <td><input type="number" name="cards[${newIdx}][startId]" value="0" style="width:60px; text-align:center"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
        </tr>`;
    } else if (tableId === 'groupsTable') {
        newRow = `<tr>
            <td><input type="text" name="groups[${newIdx}][id]" placeholder="GRUPPE" style="color:var(--accent)"></td>
            <td><input type="text" name="groups[${newIdx}][cards]" placeholder="ID1,ID2"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
        </tr>`;
    } else if (tableId === 'combosTable') {
        newRow = `<tr>
            <td><input type="text" name="combos[${newIdx}][emoji]" placeholder="🔥" style="width:50px; text-align:center"></td>
            <td><input type="text" name="combos[${newIdx}][name]" placeholder="Combo-Name"></td>
            <td><input type="number" name="combos[${newIdx}][points]" value="0" style="width:60px; text-align:center"></td>
            <td><input type="text" name="combos[${newIdx}][needs]" placeholder="ID1,ID2"></td>
            <td><input type="text" name="combos[${newIdx}][cat]" placeholder="Kategorie"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
        </tr>`;
    }
    
    table.insertAdjacentHTML('beforeend', newRow);
}

function removeRow(btn) {
    if (confirm('Möchtest du diesen Eintrag wirklich löschen?')) {
        const row = btn.parentNode.parentNode;
        row.parentNode.removeChild(row);
    }
}
</script>

</body>
</html>