<?php
// 1. Datenbankverbindung laden
require_once "db.php"; // Stellt das $pdo Objekt bereit
$pdo = getDatabaseConnection();

// 2. Zentrale Konfiguration
$config = [
    "current_version" => "1.4",
    "api_url" =>
        "https://" . $_SERVER["HTTP_HOST"] . "/shine-snatch/shine-snatch.php",
    "default_theme" => "Gold",
    "gewichtung" => false,
    "newplayergift" => true, // true = Neue Spieler erhalten sofort 1 Karte gratis, false = ausgeschaltet
    "activePack" => "", // welche textblöcke sollen genommen werden, leer lassen für zufall, wenn es keinen passenden gibt wird ein zufälliger genommen
    "admin_password_hash" =>
        '$2y$12$VQBvnaRmyhYYorVtby/J9ukVJqq7lT.7P5eST.UrzodjdJ8Ki9iZC',
    "snatchmaster" => [
        "rune-bot-id", // Rune Bot
        "517115001736134676", // Uli
        "381108532163903488", // Gregor
    ],
    "daily_winner_draw" => "all",
];

// 3. Werte aus der MySQL-Datenbank laden (mit Priorität)
// 3. Alle Einträge aus der Datenbank laden
try {
    $stmt = $pdo->query(
        "SELECT server_id, setting_key, setting_value FROM snatch_settings",
    );
    $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Strukturierte Zuweisung vornehmen
    foreach ($allSettings as $row) {
        $serverId = $row["server_id"];
        $key = $row["setting_key"];
        $value = $row["setting_value"];

        // Typen-Korrektur für Booleans (0/1 oder false/true zu echtem PHP-Boolean)
        if ($value === "true" || $value === "1") {
            $value = true;
        } elseif ($value === "false" || $value === "0") {
            $value = false;
        }

        // FALL 1: Server ID ist '0' -> Direkt auf die Hauptebene schreiben
        if ($serverId === "0") {
            $config[$key] = $value;
        }
        // FALL 2: Serverspezifischer Wert -> Unter der Server-ID verschachteln
        else {
            // Falls das Unter-Array für diesen Server noch nicht existiert, initialisieren
            if (!isset($config[$serverId])) {
                $config[$serverId] = [];
            }
            $config[$serverId][$key] = $value;
        }
    }
} catch (PDOException $e) {
    // Im Fehlerfall bleibt das PHP-Basis-Array intakt
}

// 5. Die strukturierte Konfiguration zurückgeben
return $config;
?>
