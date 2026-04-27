<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Shine-Snatch API Tester</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #eee; display: flex; gap: 20px; padding: 20px; }
        .form-container { width: 400px; background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; }
        .preview-container { flex-grow: 1; background: #6e6e6e; padding: 20px; border-radius: 8px; border: 1px solid #333; display: flex; flex-direction: column; }
        label { display: block; margin-top: 10px; font-size: 0.9em; color: #aaa; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; margin-top: 20px; padding: 12px; background: #7e22ce; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #9333ea; }
        iframe { width: 100%; height: 600px; border: none; background: #fff; border-radius: 4px; margin-top: 10px; }
        pre { background: #000; padding: 10px; font-size: 0.8em; overflow: auto; max-height: 150px; border: 1px solid #333; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>API Tester</h2>
    <form id="apiForm">
        <label>API URL</label>
        <input type="text" id="apiUrl" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/shine-snatch/shine-snatch.php">

        <label>actorName (Discord Username)</label>
        <input type="text" id="actorName" value="DeinName">

        <label>theme (z.B. kombo-theme,zufall / showtheme / set:Hexe)</label>
        <input type="text" id="theme" value="kombo-theme,zufall">

        <label>ownedCards (IDs mit Komma getrennt)</label>
        <input type="text" id="ownedCards" value="1,12,45">

        <label>world (Optional)</label>
        <input type="text" id="world" value="Test-Umgebung">

        <label>version (Optional)</label>
        <input type="text" id="version" value="1.0.0">

        <button type="button" onclick="sendRequest()">Senden & Vorschau</button>
    </form>

    <p>Letzter Payload:</p>
    <pre id="payloadDisplay">{}</pre>
</div>

<div class="preview-container">
    <h2>Vorschau</h2>
    <div id="previewArea">
        <iframe id="previewFrame"></iframe>
    </div>
</div>

<script>
async function sendRequest() {
    const url = document.getElementById('apiUrl').value;
    const frame = document.getElementById('previewFrame');
    
    // Daten sammeln
    const payload = {
        actorName: document.getElementById('actorName').value,
        theme: document.getElementById('theme').value,
        world: document.getElementById('world').value,
        version: document.getElementById('version').value,
        ownedCards: document.getElementById('ownedCards').value.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n))
    };

    document.getElementById('payloadDisplay').innerText = JSON.stringify(payload, null, 2);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        
        if (data.html) {
            // HTML in das IFrame schreiben
            const doc = frame.contentWindow.document;
            doc.open();
            doc.write('<html><head><link href="https://fonts.googleapis.com/css2?family=Signika:wght@300;400;600;700&display=swap" rel="stylesheet"><style>body{margin:0; background:#000; display:flex; justify-content:center; align-items:flex-start; padding:20px;}</style></head><body>' + data.html + '</body></html>');
            doc.close();
        } else {
            alert("Fehler: " + (data.error || "Kein HTML erhalten"));
        }
    } catch (err) {
        alert("Fetch Fehler: " + err.message);
    }
}
</script>

</body>
</html>