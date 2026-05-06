<?php
$config = require 'config.php';
$currentVersion = $config['current_version'];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Shine-Snatch API & Debug Tester</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #eee; display: flex; gap: 20px; padding: 20px; margin: 0; }
        
        /* Linke Spalte */
        .sidebar { width: 450px; display: flex; flex-direction: column; gap: 15px; }
        .form-container, .debug-container { background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; }
        
        /* Rechte Spalte */
        .preview-container { flex-grow: 1; background: #2a2a2a; padding: 20px; border-radius: 8px; border: 1px solid #333; display: flex; flex-direction: column; min-height: 95vh; }
        
        label { display: block; margin-top: 10px; font-size: 0.85em; color: #aaa; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        
        .input-group { display: flex; gap: 5px; margin-top: 5px; }
        .btn-small { width: auto; margin-top: 0; padding: 0 12px; background: #4b5563; font-size: 0.9em; border-radius: 4px; border: none; color: white; cursor: pointer; }
        .btn-small:hover { background: #6b7280; }

        button#sendBtn { width: 100%; margin-top: 20px; padding: 12px; background: #7e22ce; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button#sendBtn:hover { background: #9333ea; }
        
        .debug-title { border-bottom: 1px solid #444; padding-bottom: 5px; margin-bottom: 10px; color: #00ff00; font-size: 1em; }
        .status-badge { padding: 2px 6px; border-radius: 3px; font-size: 0.8em; font-weight: bold; }
        .status-ok { background: #065f46; color: #34d399; }
        .status-error { background: #7f1d1d; color: #f87171; }
        
        pre { background: #000; padding: 10px; font-size: 0.75em; overflow: auto; border: 1px solid #333; }
        
        /* Mehrzeilige Debug-Bereiche */
        #rawResponse { height: 200px; white-space: pre-wrap; color: #ff4444; border-color: #552222; } /* Rot für potenzielle PHP Fehler */
        #htmlCodeDisplay { height: 300px; white-space: pre-wrap; color: #38bdf8; }
        #payloadDisplay { height: 80px; color: #aaa; }

        .hex-row { color: #888; font-family: monospace; letter-spacing: 1px; font-size: 0.9em; }
        .hex-highlight { color: #facc15; font-weight: bold; }

        iframe { width: 100%; flex-grow: 1; border: none; background: #000; border-radius: 4px; margin-top: 10px; border: 1px solid #444; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="form-container">
        <h2>🚀 API Tester</h2>
        <form id="apiForm">
            <label>API URL</label>
            <input type="text" id="apiUrl" value="<?php echo $config['api_url'] ?>">

            <label>Spieler (actorName)</label>
            <input type="text" id="actorName" value="TestUser">

            <label>Theme (z.B. kombo-theme,zufall)</label>
            <input type="text" id="theme" value="kombo-theme,zufall">

            <label>Besessene Karten (ownedCards)</label>
            <input type="text" id="ownedCards" value="5,12,28">

            <label>Test-Hand (overrideHand)</label>
            <div class="input-group">
                <input type="text" id="overrideHand" placeholder="z.B. 1,2,3,4,5">
                <button type="button" class="btn-small" onclick="generateRandomHand()" title="5 einzigartige IDs (1-60)">🎲</button>
            </div>

            <label>Welt</label>
            <input type="text" id="world" value="Test-Umgebung">

            <label>Skript Version (Test-Eingabe)</label>
<div class="input-group">
    <input type="text" id="scriptVersion" value="<?php echo $currentVersion; ?>">
    <button type="button" class="btn-small" onclick="document.getElementById('scriptVersion').value='1.1'" title="Alte Version testen">v1.1</button>
</div>

            <button type="button" id="sendBtn" onclick="sendRequest()">Senden & Analysieren</button>
        </form>
    </div>

    <div class="debug-container">
        <div class="debug-title">🕵️ Rohdaten & Fehler-Analyse</div>
        <div>Status: <span id="debugStatus">-</span></div>


    <label>Sende-Payload:</label>
    <pre id="payloadDisplay">{}</pre>
        
        <label>PHP-Output / Roh-Antwort:</label>
        <pre id="rawResponse">Hier erscheinen PHP-Fehler oder das Roh-JSON...</pre>

        <label>Hex-Dump (Erste 16 Bytes):</label>
        <div id="hexDump" class="pre hex-row" style="background: #000; padding: 8px; border: 1px solid #333; margin-top: 5px;">-</div>
    </div>
</div>

<div class="preview-container">
    <h2>🖼️ Vorschau</h2>
    <iframe id="previewFrame"></iframe>

    <label>Generierter HTML Code:</label>
    <pre id="htmlCodeDisplay">Warte auf Antwort...</pre>

</div>

<script>
/**
 * Generiert 5 einzigartige Zufallszahlen zwischen 1 und 60
 */
function generateRandomHand() {
    const pool = [];
    for (let i = 1; i <= 60; i++) pool.push(i);
    
    const selected = [];
    for (let i = 0; i < 5; i++) {
        const randomIndex = Math.floor(Math.random() * pool.length);
        selected.push(pool.splice(randomIndex, 1)[0]);
    }
    
    document.getElementById('overrideHand').value = selected.sort((a,b) => a-b).join(',');
}

async function sendRequest() {
    const url = document.getElementById('apiUrl').value;
    const frame = document.getElementById('previewFrame');
    const rawPre = document.getElementById('rawResponse');
    const htmlCodePre = document.getElementById('htmlCodeDisplay');
    const payloadPre = document.getElementById('payloadDisplay');
    const hexDiv = document.getElementById('hexDump');
    const statusSpan = document.getElementById('debugStatus');
    
    const payload = {
        actorName: document.getElementById('actorName').value,
        theme: document.getElementById('theme').value,
        world: document.getElementById('world').value,
        version: "1.0.0-debug",
        scriptVersion: document.getElementById('scriptVersion').value, 
        ownedCards: document.getElementById('ownedCards').value.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n))
    };

    const ohVal = document.getElementById('overrideHand').value;
    if(ohVal.trim() !== "") {
        payload.overrideHand = ohVal.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n));
    }

    payloadPre.innerText = JSON.stringify(payload, null, 2);

    try {
        const startTime = performance.now();
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const duration = (performance.now() - startTime).toFixed(0);
        const textResponse = await response.text();
        
        // Anzeige der Rohdaten (JETZT MEHRZEILIG)
        rawPre.innerText = textResponse;
        generateHexDump(textResponse, hexDiv);

        if (response.ok) {
            statusSpan.innerHTML = `<span class="status-badge status-ok">HTTP ${response.status} OK (${duration}ms)</span>`;
            
            try {
                // Falls PHP Fehler vor dem JSON stehen, schlägt JSON.parse fehl.
                // Das ist gut, denn dann siehst du den Fehler oben in "rawResponse".
                const data = JSON.parse(textResponse);
                if (data.html) {
                    htmlCodePre.innerText = data.html;
                    const doc = frame.contentWindow.document;
                    doc.open();
                    doc.write('<html><head><link href="https://fonts.googleapis.com/css2?family=Signika:wght@300;400;600;700&display=swap" rel="stylesheet"><style>body{margin:0; background:#000; display:flex; justify-content:center; align-items:flex-start; padding:20px;}</style></head><body>' + data.html + '</body></html>');
                    doc.close();
                }
            } catch (jsonErr) {
                statusSpan.innerHTML += ` <span class="status-badge status-error">JSON FEHLER</span>`;
                htmlCodePre.innerText = "JSON konnte nicht gelesen werden. Prüfe die Roh-Antwort auf PHP-Fehlermeldungen!";
            }
        } else {
            statusSpan.innerHTML = `<span class="status-badge status-error">HTTP ${response.status} Fehler</span>`;
        }

    } catch (err) {
        statusSpan.innerHTML = `<span class="status-badge status-error">Verbindung fehlgeschlagen</span>`;
        rawPre.innerText = "FEHLER: " + err.message;
    }
}

function generateHexDump(str, container) {
    let hexResult = "";
    for (let i = 0; i < Math.min(str.length, 16); i++) {
        let hex = str.charCodeAt(i).toString(16).padStart(2, '0');
        if (i === 0 && hex === '7b') {
            hexResult += `<span class="hex-highlight">${hex}</span> `;
        } else {
            hexResult += hex + " ";
        }
    }
    container.innerHTML = hexResult + (str.length > 16 ? "..." : "");
}
</script>

</body>
</html>