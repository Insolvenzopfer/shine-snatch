<?php
// debug_snatch.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$apiUrl = "https://www.9ps.eu/shine-snatch/shine-snatch.php";

// Test-Daten (wie im Macro)
$testData = [
    "theme" => "Krark",
    "ownedCards" => [5, 12, 28] 
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// Falls SSL Probleme machen sollte (für den Test):
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title>Shine-Snatch Debugger</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .box { border: 1px solid #444; padding: 10px; margin-bottom: 20px; background: #000; }
        .error { color: #ff4444; font-weight: bold; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        .hex { color: #aaa; font-size: 0.8em; }
    </style>
</head>
<body>
    <h1>🕵️ Shine-Snatch Debug-Konsole</h1>

    <div class="box">
        <h3>1. Verbindung zum Server</h3>
        Status-Code: <?= $info['http_code'] ?><br>
        <?php if ($error): ?>
            <span class="error">Curl-Fehler: <?= $error ?></span>
        <?php else: ?>
            Verbindung erfolgreich hergestellt.
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>2. Die "Roh"-Antwort (Was Foundry sieht)</h3>
        <p>Hier dürfen KEINE Fehlermeldungen oder Leerzeilen vor der Klammer stehen:</p>
        <pre style="border: 1px dashed #555; padding: 10px;"><?= htmlspecialchars($response) ?></pre>
    </div>

    <div class="box">
        <h3>3. JSON-Check</h3>
        <?php 
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE): ?>
                ✅ JSON ist valide!<br>
                <pre><?= print_r($json, true) ?></pre>
            <?php else: ?>
                <span class="error">❌ JSON-FEHLER: <?= json_last_error_msg() ?></span><br>
                Tipp: Meistens schickt PHP eine Fehlermeldung mit, die das JSON "verunreinigt".
            <?php endif; ?>
    </div>

    <div class="box">
        <h3>4. Unsichtbare Zeichen Check (Hex-Dump)</h3>
        <p>Wenn hier am Anfang nicht <code>7b</code> (das Zeichen { ) steht, sendet dein Server Schrott mit.</p>
        <div class="hex">
            <?php 
                $hex = bin2hex(substr($response, 0, 10));
                echo "Erste 10 Bytes: " . chunk_split($hex, 2, ' ');
            ?>
        </div>
    </div>
</body>
</html>