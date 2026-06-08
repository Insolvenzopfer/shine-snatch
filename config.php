<?php
// Zentrale Konfiguration
return [
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
];
?>
