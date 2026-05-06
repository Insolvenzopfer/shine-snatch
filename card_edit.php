<?php
session_start();

// HIER DEIN PASSWORT ÄNDERN zeile auskommmentieren, seite aufrufen und in der variable darunter setzen
//echo password_hash('ShineSnatchIstSuper69!', PASSWORD_DEFAULT);

$admin_password_hash = '$2y$12$peuzsRVkOW/Q6V55HBghK.N.SYmJDb47yHULU9KEC9C0Un1rY3f9S'; 

$themesFile = 'themes.json';
$themesData = json_decode(file_get_contents($themesFile), true);

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: card_edit.php");
    exit;
}

if (isset($_POST['login_pass'])) {
    if (password_verify($_POST['login_pass'], $admin_password_hash)) {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Falsches Passwort!";
    }
}

function getDynamicStyle($id) {
    if (empty($id)) return "";
    $prefix = substr((string)$id, 0, 3);
    
    // Wir bauen einen einfachen Hash, den JS leicht nachbauen kann
    $h = 0;
    foreach (str_split($prefix) as $char) {
        $h = ($h << 5) - $h + ord($char);
    }
    
    $hue = abs($h % 360);
    return "style='border-color: hsl($hue, 70%, 50%); background: hsl($hue, 70%, 15%);'";
}

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
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔒 Card Editor Login</h2>
        <?php if (isset($error)) echo "<p style='color:#cf6679'>$error</p>"; ?>
        <form method="POST">
            <input type="password" name="login_pass" placeholder="Passwort" autofocus><br>
            <button type="submit">Einloggen</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif; 

$file = 'game_data.json';
$data = json_decode(file_get_contents($file), true);
$message = "";

if (isset($_POST['save'])) {
    $updatedData = ['cardTypes' => [], 'groups' => [], 'combos' => []];

    foreach (($_POST['cards'] ?? []) as $c) {
        if(empty($c['id'])) continue;
        $updatedData['cardTypes'][] = [
            'id' => $c['id'], 'name' => $c['name'], 'count' => (int)$c['count'],
            'points' => (int)$c['points'], 'startId' => (int)$c['startId'], 'emoji' => $c['emoji']
        ];
    }

    foreach (($_POST['groups'] ?? []) as $g) {
        if(empty($g['id'])) continue;
        $updatedData['groups'][] = [
            'id' => $g['id'],
            'cards' => array_values(array_filter($g['cards'] ?? []))
        ];
    }

    foreach (($_POST['combos'] ?? []) as $cb) {
        if(empty($cb['name'])) continue;
        $updatedData['combos'][] = [
            'emoji' => $cb['emoji'], 'name' => $cb['name'], 'points' => (int)$cb['points'],
            'needs' => array_values(array_filter($cb['needs'] ?? [])), 'cat' => $cb['cat']
        ];
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

        #selectionModal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); backdrop-filter: blur(5px);
        }
        .modal-content {
            background: #1a1a1a; margin: 5% auto; padding: 25px; border-radius: 20px;
            width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; border: 1px solid var(--accent);
        }
        .modal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-top: 20px; }
        .modal-item { 
            background: #252525; padding: 10px; border-radius: 10px; cursor: pointer; text-align: center;
            border: 1px solid transparent; transition: 0.2s;
        }
        .modal-item:hover { border-color: var(--primary); background: #303030; }
        .modal-item.empty { border-color: var(--danger); color: var(--danger); }

        .btn-add { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 10px; cursor: pointer; }
        .btn-del { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); padding: 8px; border-radius: 8px; cursor: pointer; }
        
        .save-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; border: none; padding: 15px 50px; border-radius: 50px; font-weight: 600; cursor: pointer; box-shadow: 0 10px 40px rgba(0,0,0,0.5); z-index: 100; }
        .msg { background: rgba(44, 178, 76, 0.2); color: #fff; padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; border: 1px solid var(--primary); }
        .search-input { margin-bottom: 15px; font-size: 1.1rem; padding: 12px; }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; appearance: textfield; }

        /* Basis-Stil für dynamische Chips */
.select-btn.filled {
    border-style: solid;
    border-width: 1px;
    /* Die Hintergrundfarbe und Rahmenfarbe setzen wir per JavaScript/PHP direkt im Style-Attribut */
}

/* Markierung für den aktuell ausgewählten Wert im Modal */
.modal-item.active {
    border: 2px solid var(--primary);
    background: rgba(44, 178, 76, 0.2);
    box-shadow: 0 0 10px rgba(44, 178, 76, 0.3);
}
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content: space-between; align-items: center;">
        <h1>SNATCH DATABASE CONTROL</h1>
        <a href="?logout=1" style="color: var(--danger); text-decoration: none;">✖ Logout</a>
    </div>

    <?php if($message) echo "<div class='msg'>$message</div>"; ?>

    <form method="POST" id="dbForm">
        <div class="section">
            <h2>Karten-Typen <!-- <button type="button" class="btn-add" onclick="addRow('cardsTable')">+ Neu</button> --></h2>
            <table id="cardsTable">
                <thead><tr><th>ID</th><th>Emoji</th><th>Name</th><th>Anz.</th><th>Pkt.</th><th>StartId</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['cardTypes'] as $idx => $ct): ?>
                    <tr>
                        <td><input type="text" name="cards[<?=$idx?>][id]" value="<?=$ct['id']?>" style="width:70px; color:var(--primary); text-align:center;"></td>
                        <td><input type="text" name="cards[<?=$idx?>][emoji]" value="<?=$ct['emoji']?>" style="width:50px; text-align:center;"></td>
                        <td><input type="text" name="cards[<?=$idx?>][name]" value="<?=$ct['name']?>"></td>
                        <td><input type="number" name="cards[<?=$idx?>][count]" value="<?=$ct['count']?>" style="width:60px;"></td>
                        <td><input type="number" name="cards[<?=$idx?>][points]" value="<?=$ct['points']?>" style="width:60px;"></td>
                        <td><input type="number" name="cards[<?=$idx?>][startId]" value="<?=$ct['startId']?>" style="width:70px;"></td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Gruppen & Aliase <button type="button" class="btn-add" onclick="addRow('groupsTable')">+ Neu</button></h2>
            <table id="groupsTable">
                <thead><tr><th width="200">Gruppen-ID</th><th>Karten in Gruppe (Max 12)</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['groups'] as $idx => $g): ?>
                    <tr>
                        <td><input type="text" name="groups[<?=$idx?>][id]" value="<?=$g['id']?>" style="color:var(--accent)"></td>
                        <td>
                            <div class="selection-container" data-type="group" data-row="<?=$idx?>" data-max="12">
                                <?php 
                                foreach(($g['cards'] ?? []) as $val): 
                                    $displayEmoji = '';
                                    foreach($data['cardTypes'] as $ct) { if($ct['id'] == $val) { $displayEmoji = $ct['emoji']; break; } }
                                ?>
             <button type="button" class="select-btn filled" <?=getDynamicStyle($val)?> onclick="openPicker(this)">
    <span class="label"><?=$displayEmoji?> <?=$val?></span>
    <input type="hidden" name="groups[<?=$idx?>][cards][]" value="<?=$val?>">
</button>
                                <?php endforeach; 
                                if(count($g['cards'] ?? []) < 12): ?>
                                    <button type="button" class="select-btn" onclick="openPicker(this)">
                                        <span class="label">+ Wählen</span>
                                        <input type="hidden" name="groups[<?=$idx?>][cards][]" value="">
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Kombinationen <button type="button" class="btn-add" onclick="addRow('combosTable')">+ Neu</button></h2>
            <table id="combosTable">
                <thead><tr><th>Emoji</th><th>Name</th><th>Pkt.</th><th>Bedarf (Max 5)</th><th>Kategorie</th><th width="40"></th></tr></thead>
                <tbody>
                    <?php foreach ($data['combos'] as $idx => $cb): ?>
                    <tr>
                        <td><input type="text" name="combos[<?=$idx?>][emoji]" value="<?=$cb['emoji']?>" style="width:50px; text-align:center;"></td>
                        <td><input type="text" name="combos[<?=$idx?>][name]" value="<?=$cb['name']?>"></td>
                        <td><input type="number" name="combos[<?=$idx?>][points]" value="<?=$cb['points']?>" style="width:60px;"></td>
                        <td>
                            <div class="selection-container" data-type="combo" data-row="<?=$idx?>" data-max="5">
                                <?php 
                                foreach(($cb['needs'] ?? []) as $val): 
                                    $displayEmoji = '';
                                    foreach($data['cardTypes'] as $ct) { if($ct['id'] == $val) { $displayEmoji = $ct['emoji']; break; } }
                                    if(empty($displayEmoji)) $displayEmoji = '📁';
                                ?>
<button type="button" class="select-btn filled" <?=getDynamicStyle($val)?> onclick="openPicker(this)">
    <span class="label"><?=($displayEmoji ?: '📁')?> <?=$val?></span>
    <input type="hidden" name="combos[<?=$idx?>][needs][]" value="<?=$val?>">
</button>
                                <?php endforeach; 
                                if(count($cb['needs'] ?? []) < 5): ?>
                                    <button type="button" class="select-btn" onclick="openPicker(this)">
                                        <span class="label">+ Wählen</span>
                                        <input type="hidden" name="combos[<?=$idx?>][needs][]" value="">
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
    <select name="combos[<?=$idx?>][cat]" style="width: 100%; background: rgba(0,0,0,0.4); color: white; border: 1px solid rgba(255,255,255,0.1); padding: 8px; border-radius: 8px;">
        <option value="">-- Kein Theme --</option>
        <?php 
        if (!empty($themesData)) {
            foreach ($themesData as $themeKey => $themeValues) { 
                // Wir nutzen den Key (z.B. "Gold") als ID
                // Und schauen nach einem Emoji (falls vorhanden, sonst Standard)
                $name = $themeKey;
                $emoji = $themeValues['specialCardEmoji'] ?? '✨';
                ?>
                <option value="<?= htmlspecialchars($themeKey) ?>" <?= (isset($cb['cat']) && $cb['cat'] == $themeKey) ? 'selected' : '' ?>>
                    <?= $name ?> <?= $emoji ?> <!-- Name nach vorne gestellt -->
                </option>
                <?php 
            }
        }
        ?>
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
        <h3 id="modalTitle">Karte oder Gruppe wählen</h3>
        <input type="text" id="modalSearch" class="search-input" placeholder="Suchen..." oninput="filterModal()">
        <div class="modal-grid" id="modalGrid"></div>
        <div style="margin-top: 20px; text-align: right;">
            <button type="button" class="btn-add" style="background:#444" onclick="closePicker()">Abbrechen</button>
        </div>
    </div>
</div>

<script>
let currentTargetBtn = null;
const allCardOptions = <?= json_encode($data['cardTypes']) ?>;
const allGroupOptions = <?= json_encode(array_column($data['groups'], 'id')) ?>;

function addRow(tableId) {
    const table = document.getElementById(tableId).getElementsByTagName('tbody')[0];
    const idx = Date.now();
    const allThemes = <?= json_encode($themesData) ?>;
    let row = document.createElement('tr');

    if (tableId === 'cardsTable') {
        row.innerHTML = `
            <td><input type="text" name="cards[${idx}][id]" style="width:70px; color:var(--primary); text-align:center;"></td>
            <td><input type="text" name="cards[${idx}][emoji]" style="width:50px; text-align:center;"></td>
            <td><input type="text" name="cards[${idx}][name]"></td>
            <td><input type="number" name="cards[${idx}][count]" value="0" style="width:60px;"></td>
            <td><input type="number" name="cards[${idx}][points]" value="0" style="width:60px;"></td>
            <td><input type="number" name="cards[${idx}][startId]" value="0" style="width:70px;"></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>`;
    } else if (tableId === 'groupsTable') {
        row.innerHTML = `
            <td><input type="text" name="groups[${idx}][id]" style="color:var(--accent)"></td>
            <td><div class="selection-container" data-type="group" data-row="${idx}" data-max="12">
                <button type="button" class="select-btn" onclick="openPicker(this)">
                    <span class="label">+ Wählen</span>
                    <input type="hidden" name="groups[${idx}][cards][]" value="">
                </button>
            </div></td>
            <td><button type="button" class="btn-del" onclick="removeRow(this)">✕</button></td>`;
    } else if (tableId === 'combosTable') {
    let themeOptions = '<option value="">-- Kein Theme --</option>';
    
    // Da themesData ein Objekt ist, nutzen wir Object.entries
if (allThemes) {
    Object.entries(allThemes).forEach(([key, value]) => {
        let emoji = value.specialCardEmoji || '✨';
        // Hier auch: Name vor das Emoji stellen
        themeOptions += `<option value="${key}">${key} ${emoji}</option>`;
    });
}

    row.innerHTML = `
        <td><input type="text" name="combos[${idx}][emoji]" style="width:50px; text-align:center;"></td>
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
    const modal = document.getElementById('selectionModal');
    const grid = document.getElementById('modalGrid');
    const type = btn.closest('.selection-container').dataset.type;
    const currentValue = btn.querySelector('input').value;
    
    grid.innerHTML = '';

    const emptyDiv = document.createElement('div');
    emptyDiv.className = 'modal-item empty' + (currentValue === '' ? ' active' : '');
    emptyDiv.onclick = () => selectOption('');
    emptyDiv.innerHTML = '❌ LEEREN / LÖSCHEN';
    grid.appendChild(emptyDiv);
    
    allCardOptions.forEach(c => {
        const item = document.createElement('div');
        item.className = 'modal-item' + (currentValue === c.id ? ' active' : '');
        item.onclick = () => selectOption(c.id, c.emoji);
        item.innerHTML = `${c.emoji} ${c.id} <br><small>${c.name}</small>`;
        grid.appendChild(item);
    });

    if(type === 'combo') {
        allGroupOptions.forEach(g => {
            const item = document.createElement('div');
            item.style.borderColor = 'var(--accent)';
            item.className = 'modal-item' + (currentValue === g ? ' active' : '');
            item.onclick = () => selectOption(g, '📁');
            item.innerHTML = `📁 GRUPPE: ${g}`;
            grid.appendChild(item);
        });
    }

    modal.style.display = 'block';
    const searchInput = document.getElementById('modalSearch');
    searchInput.value = '';
    filterModal(); 
    searchInput.focus();

    setTimeout(() => {
        const activeItem = grid.querySelector('.modal-item.active');
        if (activeItem) activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 10);
}

function closePicker() {
    document.getElementById('selectionModal').style.display = 'none';
}

function filterModal() {
    const q = document.getElementById('modalSearch').value.toLowerCase();
    document.querySelectorAll('.modal-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(q) ? 'block' : 'none';
    });
}

function selectOption(val, emoji = '') {
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
    const fieldName = type === 'group' ? 'cards' : 'needs';

    values.forEach(v => {
        const cardDoc = allCardOptions.find(c => c.id == v);
        const emoji = cardDoc ? cardDoc.emoji : (type === 'combo' ? '📁' : '');
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