<?php
date_default_timezone_set("Europe/Berlin");

// === CORS-HEADER FÜR FOUNDRY HINZUFÜGEN ===
header("Access-Control-Allow-Origin: *"); // Erlaubt Foundry den Zugriff von jeder Domain/IP
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Erlaubt die nötigen Anfrage-Methoden
header(
    "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With",
);

// Wenn der Browser vorab eine "OPTIONS"-Anfrage (Preflight) schickt, direkt mit 200 antworten und beenden
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header("Content-Type: application/json; charset=utf-8");

// 1. Config & Zentrale DB-Verbindung laden
$config = require "config.php";
require_once "db.php";
require_once "../php/telegramsend.php";
$pdo = getDatabaseConnection();

$input = json_decode(file_get_contents("php://input"), true) ?? [];

// ==========================================================
// HILFSFUNKTION: Prüft, ob ein User bereits in der DB existiert
// ==========================================================
function userExists($targetString, $pdo)
{
    // Falls es ein Discord-Ping ist, extrahieren wir die reine ID
    if (
        str_starts_with($targetString, "<@") &&
        str_ends_with($targetString, ">")
    ) {
        $cleanId = preg_replace("/[^0-9]/", "", $targetString);
        $stmt = $pdo->prepare(
            "SELECT 1 FROM snatch_users WHERE actor_id = ? LIMIT 1",
        );
        $stmt->execute([$cleanId]);
        return (bool) $stmt->fetchColumn();
    }

    // Ansonsten suchen wir im actor_name ODER display_name (case-insensitive)
    $stmt = $pdo->prepare("
        SELECT 1 FROM snatch_users
        WHERE LOWER(actor_name) = ? OR LOWER(display_name) = ?
        LIMIT 1
    ");
    $stmt->execute([strtolower($targetString), strtolower($targetString)]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Holt einen zufälligen Text basierend auf Paket und Gruppe mit intelligentem Fallback.
 *
 * @param PDO $pdo Die Datenbankverbindung
 * @param string $pack Das gewünschte Text-Paket (z.B. 'gruppenname_1')
 * @param string $group Die benötigte Rolle (z.B. 'winner_champion')
 * @param array $replacements Die Platzhalter zum Ersetzen
 * @return string Der fertige Text
 */
function getRandomSnatchText(
    PDO $pdo,
    string $pack,
    string $group,
    array $replacements = [],
): string {
    try {
        // --- SCHRITT 1: Exakte Suche (Gewünschtes Paket + Richtige Gruppe) ---
        $stmt = $pdo->prepare(
            "SELECT content FROM snatch_texts WHERE text_pack = ? AND text_group = ?",
        );
        $stmt->execute([$pack, $group]);
        $texts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // --- SCHRITT 2: Fallback auf das 'default' Paket für diese Gruppe ---
        if (empty($texts) && $pack !== "default") {
            $stmt = $pdo->prepare(
                "SELECT content FROM snatch_texts WHERE text_pack = 'default' AND text_group = ?",
            );
            $stmt->execute([$group]);
            $texts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // --- SCHRITT 3: Fallback auf IRGENDEIN Paket, Hauptsache die Gruppe stimmt ---
        if (empty($texts)) {
            $stmt = $pdo->prepare(
                "SELECT content FROM snatch_texts WHERE text_group = ?",
            );
            $stmt->execute([$group]);
            $texts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // --- SCHRITT 4: Letzter Notnagel (Die DB hat gar nichts zu dieser Gruppe) ---
        if (empty($texts)) {
            $stmt = $pdo->query("SELECT content FROM snatch_texts");
            $texts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($texts)) {
                return "⚠️ [Die Text-Datenbank ist absolut leer!]";
            }
        }
        // Zufälligen Text aus den ermittelten Treffern auswählen
        $chosenText = $texts[array_rand($texts)];

        // Platzhalter ersetzen
        $finalText = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $chosenText,
        );

        // Hier IMMER ein "\n" in DOPPELTEN Anführungszeichen anhängen!
        return $finalText . "\n";

        /*
        // Zufälligen Text aus den ermittelten Treffern auswählen
        $chosenText = $texts[array_rand($texts)];

        // Platzhalter ersetzen
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $chosenText,
        );
        */
    } catch (PDOException $e) {
        error_log("Fehler in getRandomSnatchText: " . $e->getMessage());
        return "❌ Fehler beim Laden des Textes.";
    }
}

// ==========================================================
// HILFSFUNKTION: User laden oder dynamisch anlegen (Zentral mit Discord-Ping Erkennung)
// ==========================================================
function getOrCreateUser($input, $pdo, $onlyIfExisting = false)
{
    // <-- NEU: optionaler Parameter
    global $config;
    $actorId = !empty($input["actorId"])
        ? trim((string) $input["actorId"])
        : null;
    $serverId = !empty($input["serverId"])
        ? trim((string) $input["serverId"])
        : null;
    $actorName = !empty($input["actorName"])
        ? trim((string) $input["actorName"])
        : "Unbekannter Held";
    $playerName = !empty($input["playerName"])
        ? trim((string) $input["playerName"])
        : $actorName;

    // --- ZENTRALE DISCORD-PING ERKENNUNG ---
    if ($actorId === null) {
        if (
            str_starts_with($actorName, "<@") &&
            str_ends_with($actorName, ">")
        ) {
            $actorId = preg_replace("/[^0-9]/", "", $actorName);
        } elseif (
            str_starts_with($playerName, "<@") &&
            str_ends_with($playerName, ">")
        ) {
            $actorId = preg_replace("/[^0-9]/", "", $playerName);
        }
    }

    $finalActorId = $actorId ?? trim((string) $actorName);

    // Suche in der Datenbank anhand der ID
    $stmt = $pdo->prepare(
        "SELECT * FROM snatch_users WHERE actor_id = ? AND (server_id = ? OR server_id IS NULL OR server_id = '') LIMIT 1",
    );
    $stmt->execute([$finalActorId, $serverId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // NEU: Wenn $onlyIfExisting aktiv ist, verändern wir AUF KEINEN FALL die Namen in der DB
        if ($onlyIfExisting) {
            return $user;
        }

        // Falls wir den User über einen Ping gefunden haben, wollen wir seinen actor_name
        // nicht mit "<@517115...>" überschreiben! Wir updaten nur, wenn es ein echter Name ist.
        $isNameValid =
            !str_starts_with($actorName, "<@") &&
            !empty($actorName) &&
            !str_starts_with(strtolower($actorName), "discord-user-");
        $isPlayerValid =
            !str_starts_with($playerName, "<@") &&
            !empty($playerName) &&
            !str_starts_with(strtolower($playerName), "discord-user-");

        $newActorName = $isNameValid ? $actorName : $user["actor_name"];
        $newDisplayName = $isPlayerValid ? $playerName : $user["display_name"];

        $shouldUpdateServerId = empty($user["server_id"]) && !empty($serverId);

        // Nur updaten, wenn sich tatsächlich ein echter Textname oder die Server-ID geändert hat
        if (
            $user["actor_name"] !== $newActorName ||
            $user["display_name"] !== $newDisplayName ||
            $shouldUpdateServerId
        ) {
            $stmtUpdate = $pdo->prepare(
                "UPDATE snatch_users SET actor_name = ?, display_name = ?, server_id = ? WHERE actor_id = ?",
            );
            $stmtUpdate->execute([
                $newActorName,
                $newDisplayName,
                $serverId ?? ($user["server_id"] ?? ""),
                $finalActorId,
            ]);

            $user["actor_name"] = $newActorName;
            $user["display_name"] = $newDisplayName;
            $user["server_id"] = $serverId ?? ($user["server_id"] ?? "");
        }

        return $user;
    }

    // NEU: Wenn der User nicht existiert und wir ihn nicht anlegen dürfen, brechen wir hier ab!
    if ($onlyIfExisting) {
        return null;
    }

    // Neuer User: Anlage in der Datenbank (wie gehabt)
    $insertActorName =
        !str_starts_with($actorName, "<@") &&
        !str_starts_with(strtolower($actorName), "discord-user-")
            ? $actorName
            : "User #" . substr($finalActorId, -4);
    $insertPlayerName =
        !str_starts_with($playerName, "<@") &&
        !str_starts_with(strtolower($playerName), "discord-user-")
            ? $playerName
            : $insertActorName;

    $finalServerId = $serverId ?? "";

    $stmtInsert = $pdo->prepare(
        "INSERT INTO snatch_users (actor_id, server_id, actor_name, display_name) VALUES (?, ?, ?, ?)",
    );
    $stmtInsert->execute([
        $finalActorId,
        $finalServerId,
        $insertActorName,
        $insertPlayerName,
    ]);

    $stmt = $pdo->prepare(
        "SELECT * FROM snatch_users WHERE actor_id = ? AND server_id = ?",
    );
    $stmt->execute([$finalActorId, $finalServerId]);
    $newUser = $stmt->fetch(PDO::FETCH_ASSOC);

    global $config;
    $giftEnabled = isset($config["newplayergift"])
        ? (bool) $config["newplayergift"]
        : false;

    if ($giftEnabled && $newUser) {
        $giftCard = generateWeightedCardFromDb($pdo, $config);
        if ($giftCard) {
            $stmtGift = $pdo->prepare(
                "INSERT INTO snatch_cards (user_id, card_id, card_name, emoji, category) VALUES (?, ?, ?, ?, ?)",
            );
            $stmtGift->execute([
                $newUser["id"],
                $giftCard["id"],
                $giftCard["name"],
                $giftCard["emoji"],
                $giftCard["category"],
            ]);
            $newUser["gift_card"] = $giftCard;
        }
    }

    return $newUser;
}

// ==========================================================
// HILFSFUNKTION: Gewichtete Karte generieren (Aus DB-Pool) - Erweitert für Zusätze
// ==========================================================
function generateWeightedCardFromDb(
    $pdo,
    $config = [],
    $forcedCategoryName = null,
    $forcedCardName = null,
) {
    $category = null;

    // FALL A: Kategorie wurde fest vorgegeben
    if (!empty($forcedCategoryName)) {
        $stmt = $pdo->prepare(
            "SELECT * FROM snatch_game_card_types WHERE LOWER(name) = ? LIMIT 1",
        );
        $stmt->execute([strtolower(trim($forcedCategoryName))]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // FALL B: Keine Kategorie angegeben oder angegebene Kategorie existiert nicht -> Zufall
    if (!$category) {
        // Prüfen, ob die Gewichtung in der Config aktiv ist (Standard: true, falls nicht gesetzt)
        $useWeighting = isset($config["gewichtung"])
            ? (bool) $config["gewichtung"]
            : true;

        if ($useWeighting) {
            // MIT ERHÖHUNG: Der alte gewichtete Pool (kleine IDs sind wahrscheinlicher)
            $weightedPool = [];
            for ($i = 1; $i <= 60; $i++) {
                $weight = 61 - $i;
                for ($w = 0; $w < $weight; $w++) {
                    $weightedPool[] = $i;
                }
            }
            $drawnId = $weightedPool[array_rand($weightedPool)];
        } else {
            // OHNE ERHÖHUNG: Jede Zahl von 1 bis 60 hat die exakt gleiche Chance
            $drawnId = rand(1, 60);
        }

        $stmt = $pdo->query(
            "SELECT * FROM snatch_game_card_types ORDER BY start_id DESC",
        );
        $cardTypes = $stmt->fetchAll();

        foreach ($cardTypes as $type) {
            if ($drawnId >= $type["start_id"]) {
                $category = $type;
                break;
            }
        }
    }

    // ID für die Karte bestimmen
    $finalCardId = isset($drawnId)
        ? $drawnId
        : rand(
            (int) $category["start_id"],
            (int) $category["start_id"] + (int) $category["count"] - 1,
        );

    // Name bestimmen
    if (!empty($forcedCardName)) {
        // Admin hat einen festen Namen erzwungen -> Keine Zusätze anhängen!
        $name = trim($forcedCardName);
    } else {
        // Name aus dem Pool der Kategorie auswürfeln
        $pool = [];
        if (!empty($category["name_pool"])) {
            $pool = json_decode($category["name_pool"], true);
        }

        if (empty($pool)) {
            $pool = ["Geheimnisvolle Karte"];
        }

        $name = $pool[array_rand($pool)];

        // === Dynamischen Kartenzusatz aus der Datenbank anhängen ===
        try {
            // Holt exakt einen zufälligen Eintrag aus der snatch_kartenzusatz Tabelle
            $zusatzStmt = $pdo->query(
                "SELECT zusatzname FROM snatch_kartenzusatz ORDER BY RAND() LIMIT 1",
            );
            $zufallsZusatz = $zusatzStmt->fetchColumn();

            if ($zufallsZusatz) {
                $name .= " " . trim($zufallsZusatz);
            }
        } catch (PDOException $e) {
            // Fallback, falls die Tabelle mal leer oder nicht erreichbar ist (verhindert Spielabsturz)
            error_log(
                "Fehler beim Laden des Kartenzusatzes: " . $e->getMessage(),
            );
        }

        /*         if (strpos($category["name"] ?? "", "/") !== false) {
            $parts = explode("/", $category["name"]);
            $name .= " (Mächtiger " . trim($parts[array_rand($parts)]) . ")";
        }
        */
    }

    return [
        "id" => $finalCardId,
        "name" => $name,
        "emoji" => $category["emoji"] ?? "🃏",
        "category" => $category["name"] ?? "Unbekannt",
    ];
}

// ----------------------------------------------------------
// Hauptdaten des aktuellen Spielers laden
// ----------------------------------------------------------
$dbUser = getOrCreateUser($input, $pdo);
$actorPing = "**<@{$dbUser["actor_id"]}>**";

// Definiere die Theme-Variable aus dem Input
$theme = isset($input["theme"]) ? trim($input["theme"]) : "";

// ==========================================================
// REGEL 1: SET:THEME (SQL Update)
// ==========================================================
if (str_starts_with(strtolower($theme), "set:")) {
    $chosenTheme = trim(substr($theme, 4));

    if (empty($chosenTheme)) {
        $pdo->prepare(
            "UPDATE snatch_users SET theme = 'Gold' WHERE id = ?",
        )->execute([$dbUser["id"]]);
        $theme = "Gold";
        $input["theme"] = "Gold";
        $msg = "♻️ Dein Standard-Theme wurde auf Gold zurückgesetzt.";
    }
    // --- FALL 1: KOMMA VORHANDEN (Beliebige Kombination) ---
    elseif (str_contains($chosenTheme, ",")) {
        $themes = array_map("trim", explode(",", $chosenTheme));
        $validatedThemes = [];

        foreach ($themes as $t) {
            $tLower = strtolower($t);

            // Wenn es ein Spezial-Theme ist, einfach so übernehmen
            if ($tLower === "zufall" || $tLower === "kombo-theme") {
                $validatedThemes[] = $tLower; // oder $t für Originalschreibweise
            } else {
                // Ansonsten in der Datenbank nachschlagen
                $stmt = $pdo->prepare(
                    "SELECT theme_name FROM snatch_themes WHERE LOWER(theme_name) = ?",
                );
                $stmt->execute([$tLower]);
                $dbThemeResult = $stmt->fetchColumn();

                if (!$dbThemeResult) {
                    echo json_encode([
                        "text" => "❌ Das Theme **{$t}** innerhalb deiner Auswahl existiert nicht!",
                    ]);
                    exit();
                }
                $validatedThemes[] = $dbThemeResult; // Korrekte Schreibweise aus der DB
            }
        }

        // Alle validierten Teile wieder mit Komma zusammenfügen
        $finalThemeString = implode(",", $validatedThemes);

        $pdo->prepare(
            "UPDATE snatch_users SET theme = ? WHERE id = ?",
        )->execute([$finalThemeString, $dbUser["id"]]);

        $theme = $finalThemeString;
        $input["theme"] = $finalThemeString;

        $msg = "🎨 Deine Theme-Auswahl wurde erfolgreich gespeichert: **{$finalThemeString}**!";
    }
    // --- FALL 2: KEIN KOMMA VORHANDEN (Einzel-Theme) ---
    else {
        $stmt = $pdo->prepare(
            "SELECT theme_name FROM snatch_themes WHERE LOWER(theme_name) = ?",
        );
        $stmt->execute([strtolower($chosenTheme)]);
        $dbThemeResult = $stmt->fetchColumn();

        $isSpecialTheme = in_array(strtolower($chosenTheme), [
            "zufall",
            "kombo-theme",
        ]);

        if ($dbThemeResult || $isSpecialTheme) {
            $actualThemeName = $dbThemeResult ?: $chosenTheme;

            $pdo->prepare(
                "UPDATE snatch_users SET theme = ? WHERE id = ?",
            )->execute([$actualThemeName, $dbUser["id"]]);

            $theme = $actualThemeName;
            $input["theme"] = $actualThemeName;

            $msg = "🎨 Theme erfolgreich für dich als Standard gespeichert: **{$actualThemeName}**!";
        } else {
            echo json_encode([
                "text" => "❌ Das Theme **{$chosenTheme}** existiert nicht!",
            ]);
            exit();
        }
    }
}

// ==========================================================
// REGEL 2: GIVECARD (Schenken via SQL-IDs)
// ==========================================================
elseif (str_starts_with(strtolower($theme), "givecard")) {
    $parts = preg_split("/\s+/", trim($theme));
    $targetCardId = isset($parts[1]) ? (int) $parts[1] : 0;

    $targetPlayerName = "";
    if (count($parts) > 2) {
        $targetPlayerName = implode(" ", array_slice($parts, 2));
    }

    if ($targetCardId <= 0 || empty($targetPlayerName)) {
        echo json_encode([
            "text" =>
                "⚠️ Syntax: `!snatch givecard [KartenID] [Spielername/Erwähnung]`",
        ]);
        exit();
    }

    // --- NEU: EXISTENZ-CHECK GEGEN TIPPFEHLER ---
    if (!userExists($targetPlayerName, $pdo)) {
        echo json_encode([
            "text" => "❌ Der Spieler **{$targetPlayerName}** wurde im Snatch-System nicht gefunden! Bitte überprüfe die Schreibweise oder lass ihn erst einmal `!snatch` spielen.",
        ]);
        exit();
    }

    // Ab hier ist sicher: Der User existiert! Wir bereiten die Parameter für das Laden vor
    $userParams = [
        "serverId" => !empty($input["serverId"])
            ? trim((string) $input["serverId"])
            : $dbUser["server_id"] ?? "", // <-- NEU
    ];
    if (
        str_starts_with($targetPlayerName, "<@") &&
        str_ends_with($targetPlayerName, ">")
    ) {
        $userParams["actorId"] = preg_replace(
            "/[^0-9]/",
            "",
            $targetPlayerName,
        );
    } else {
        $userParams["actorName"] = $targetPlayerName;
    }

    // Ziel-User sicher aus DB laden
    $targetUser = getOrCreateUser($userParams, $pdo);

    // Generiere die echten Discord-Pings über die actor_id der Profile
    $actorPing = "<@{$dbUser["actor_id"]}>";
    $targetPing = "<@{$targetUser["actor_id"]}>";

    // Prüfen, ob der aktuelle Schenkende die Karte überhaupt besitzt
    $stmt = $pdo->prepare(
        "SELECT * FROM snatch_cards WHERE user_id = ? AND card_id = ? LIMIT 1",
    );
    $stmt->execute([$dbUser["id"], $targetCardId]);
    $giftCard = $stmt->fetch();

    if (!$giftCard) {
        echo json_encode([
            "text" => "❌ {$actorPing}, du besitzt die Karte #{$targetCardId} nicht!",
        ]);
        exit();
    }

    // Prüfen, ob der Ziel-User diese Karte bereits im Album hat
    $stmt = $pdo->prepare(
        "SELECT 1 FROM snatch_cards WHERE user_id = ? AND card_id = ? LIMIT 1",
    );
    $stmt->execute([$targetUser["id"], $targetCardId]);
    if ($stmt->fetch()) {
        echo json_encode([
            "text" => "🎁 Transaktion abgebrochen! {$targetPing} besitzt die Karte #{$targetCardId} bereits.",
        ]);
        exit();
    }

    // Karte in der DB umschreiben (Besitzer wechseln)
    $pdo->prepare("UPDATE snatch_cards SET user_id = ? WHERE id = ?")->execute([
        $targetUser["id"],
        $giftCard["id"],
    ]);

    // Erfolgsausgabe mit den aktiven Discord-Pings
    echo json_encode([
        "text" => "🎁 **Geschenk!** 👤 {$actorPing} schenkt 👤 {$targetPing} die Karte {$giftCard["emoji"]} **{$giftCard["card_name"]}** (#{$targetCardId})!",
    ]);
    exit();
}

// ==========================================================
// REGEL 2b: CREATECARD (Admin-Befehl für Snatchmaster)
// ==========================================================
elseif (str_starts_with(strtolower($theme), "createcard")) {
    // Admin-Rechte prüfen aus der config.php
    $allowedMasters = is_array($config["snatchmaster"])
        ? $config["snatchmaster"]
        : [];
    if (!in_array($dbUser["actor_id"], $allowedMasters)) {
        echo json_encode([
            "text" =>
                "❌ Du hast keine Berechtigung, diesen Befehl auszuführen!",
        ]);
        exit();
    }

    // Intelligenteres Text-Parsing, das Anführungszeichen für Leerzeichen erlaubt
    // z.B. createcard "Schwert des Schicksals" "Krieger" insolvenzopfer
    preg_match_all('/"([^"]+)"|\S+/', $theme, $matches);
    $args = isset($matches[0]) ? $matches[0] : [];

    // Anführungszeichen aus den Argumenten entfernen
    foreach ($args as $key => $val) {
        $args[$key] = trim($val, '"\'');
    }

    // Erwartete Argumente aufdröseln:
    // !snatch createcard -> $args[0]
    // Je nachdem wie viele Argumente übergeben wurden, ordnen wir sie zu.
    $forcedName = null;
    $forcedCat = null;
    $targetPlayerName = null;

    if (count($args) == 2) {
        // Syntax: !snatch createcard [Spieler] -> Komplett zufällige Karte generieren
        $targetPlayerName = $args[1];
    } elseif (count($args) == 3) {
        // Syntax: !snatch createcard [Name] [Spieler] -> Name fixiert, Rest Zufall
        $forcedName = $args[1];
        $targetPlayerName = $args[2];
    } elseif (count($args) >= 4) {
        // Syntax: !snatch createcard [Name] [Kategorie] [Spieler] -> Name & Kategorie fixiert
        $forcedName = $args[1];
        $forcedCat = $args[2];
        $targetPlayerName = $args[3];
    }

    // Wenn Felder als "zufall" oder "-" deklariert wurden, ignorieren wir sie (wird zu null)
    if (strtolower($forcedName ?? "") === "zufall" || $forcedName === "-") {
        $forcedName = null;
    }
    if (strtolower($forcedCat ?? "") === "zufall" || $forcedCat === "-") {
        $forcedCat = null;
    }

    if (empty($targetPlayerName)) {
        echo json_encode([
            "text" =>
                "⚠️ **Syntax für Snatchmaster:**\n" .
                "• Komplett Zufall: `!snatch createcard [Spieler]`\n" .
                "• Fester Name: `!snatch createcard \"Karten-Name\" [Spieler]`\n" .
                "• Alles fest: `!snatch createcard \"Karten-Name\" \"Kategorie\" [Spieler]`\n" .
                "*(Nutze \"zufall\" oder \"-\" um Argumente zu überspringen!)*",
        ]);
        exit();
    }

    // ==========================================
    // SPIELER-DATEN FÜR MULTI-SERVER AUFBEREITEN
    // ==========================================
    $currentServerId = !empty($input["serverId"])
        ? trim((string) $input["serverId"])
        : $dbUser["server_id"] ?? "";

    $userParams = [
        "serverId" => $currentServerId,
    ];

    if (
        str_starts_with($targetPlayerName, "<@") &&
        str_ends_with($targetPlayerName, ">")
    ) {
        $userParams["actorId"] = preg_replace(
            "/[^0-9]/",
            "",
            $targetPlayerName,
        );
        $userParams["actorName"] = "Discord-User-" . $userParams["actorId"];
    } else {
        // Falls kein Ping, sondern ein Name eingegeben wurde, suchen wir nach diesem actor_name
        $userParams["actorName"] = $targetPlayerName;
    }

    // NEU: Wir übergeben TRUE als 3. Parameter ($onlyIfExisting).
    // Das verhindert das Überschreiben alter Namen und das ungewollte Neuanlegen!
    $targetUser = getOrCreateUser($userParams, $pdo, true);

    $actorPing = "<@{$dbUser["actor_id"]}>";

    // NEU: Abbruch, falls der Spieler nicht existiert
    if (!$targetUser) {
        echo json_encode([
            "text" => "❌ **Fehler beim Kartendruck!** Der Spieler **{$targetPlayerName}** hat noch kein Snatch-Profil auf diesem Server. Er muss zuerst mindestens 1x normal mitspielen oder würfeln!",
        ]);
        exit();
    }

    $targetPing = "<@{$targetUser["actor_id"]}>";

    // Karte mithilfe der modifizierten Funktion generieren
    $generatedCard = generateWeightedCardFromDb(
        $pdo,
        $config,
        $forcedCat,
        $forcedName,
    );

    // Duplikatsprüfung: Besitzt der User DIESE spezifische ID bereits?
    $stmt = $pdo->prepare(
        "SELECT 1 FROM snatch_cards WHERE user_id = ? AND card_id = ?",
    );
    $stmt->execute([$targetUser["id"], $generatedCard["id"]]);

    if ($stmt->fetch()) {
        echo json_encode([
            "text" => "🧙‍♂️ **Magie fehlgeschlagen!** {$actorPing}, die generierte Karte {$generatedCard["emoji"]} **{$generatedCard["name"]}** (#{$generatedCard["id"]}) befindet sich bereits im Album von {$targetPing}.",
        ]);
        exit();
    }

    // In die Datenbank des Ziel-Users injizieren
    $stmt = $pdo->prepare(
        "INSERT INTO snatch_cards (user_id, card_id, card_name, emoji, category) VALUES (?, ?, ?, ?, ?)",
    );
    $stmt->execute([
        $targetUser["id"],
        $generatedCard["id"],
        $generatedCard["name"],
        $generatedCard["emoji"],
        $generatedCard["category"],
    ]);

    echo json_encode([
        "text" => getRandomSnatchText(
            $pdo,
            $config["activePack"],
            "createcard",
            [
                "{actorPing}" => $actorPing,
                "{targetPing}" => $targetPing,
                "{generatedCardemoji}" => $generatedCard["emoji"],
                "{generatedCardname}" => $generatedCard["name"],
                "{generatedCardid}" => $generatedCard["id"],
                "{generatedCardcategory}" => $generatedCard["category"],
            ],
        ),
    ]);
    exit();
}

// ==========================================================
// REGEL 3: SHOWCARDS (SQL Album ausgeben)
// ==========================================================
elseif (strtolower($theme) === "showcards") {
    $stmt = $pdo->prepare(
        "SELECT card_id, card_name, emoji, category FROM snatch_cards WHERE user_id = ? ORDER BY card_id ASC",
    );
    $stmt->execute([$dbUser["id"]]);
    $myCards = $stmt->fetchAll();

    if (empty($myCards)) {
        echo json_encode([
            "text" => "📖 {$actorPing}, dein Album ist noch leer! Gewinne das tägliche Event, um Karten zu erhalten.",
        ]);
        exit();
    }
    $output = "📖 **Sammelkarten-Album von {$actorPing}:**\n";
    foreach ($myCards as $card) {
        $output .= "• {$card["emoji"]} **{$card["card_name"]}** (#{$card["card_id"]} | *{$card["category"]}*)\n";
    }
    echo json_encode(["text" => $output]);
    exit();
}

// ==========================================================
// REGEL 3b: SHOWTHEME (Aktives Theme des Users separat abfragen)
// ==========================================================
elseif (strtolower($theme) === "showtheme") {
    // Das aktuell gesetzte Theme aus dem bereits geladenen $dbUser-Objekt auslesen
    // Falls das Feld aus irgendeinem Grund leer ist, nutzen wir 'Gold' als Standard-Fallback
    $currentTheme = !empty($dbUser["theme"]) ? $dbUser["theme"] : "Gold";

    echo json_encode([
        "text" => "🎨 {$actorPing}, dein aktuell ausgerüstetes Theme ist: **{$currentTheme}**",
    ]);
    exit();
}

// ==========================================================
// REGEL 4: GET_DAILY_WINNER / WINNER (Tages-Event Auswertung)
// ==========================================================
if (
    strtolower($theme) === "get_daily_winner" ||
    strtolower($theme) === "winner"
) {
    $allowedMasters = is_array($config["snatchmaster"])
        ? $config["snatchmaster"]
        : [];
    if (!in_array($dbUser["actor_id"], $allowedMasters)) {
        echo json_encode([
            "text" => getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "no_permission",
            ),
        ]);
        exit();
    }

    $world = $input["world"] ?? "Unbekannt";

    if (empty($world) || $world === "Unbekannt") {
        echo json_encode([
            "text" => getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "no_data",
            ),
        ]);
        exit();
    }

    try {
        // ANTI-CHEAT-ABFRAGE:
        // Holt für jeden User ausschließlich das ALLERERSTE Spiel des heutigen Tages (MIN(l.id))
        // und sortiert die Liste nach den Punkten absteigend.
        // aktuell nur der aktuelle tag,für die letzten 24 h , den where in das hier ändern
        // WHERE server_name = ? AND created_at >= NOW() - INTERVAL 1 DAY
        $stmtWinner = $pdo->prepare("
            SELECT l.*, u.display_name, u.actor_id
            FROM snatch_logs l
            JOIN snatch_users u ON l.user_id = u.id
            WHERE l.id IN (
                SELECT MIN(id)
                FROM snatch_logs
                WHERE server_name = ? AND DATE(created_at) = CURDATE()
                GROUP BY user_id
            )
            ORDER BY l.total_points DESC
        ");
        $stmtWinner->execute([$world]);
        $todayLogs = $stmtWinner->fetchAll(PDO::FETCH_ASSOC);

        if (empty($todayLogs)) {
            echo json_encode([
                getRandomSnatchText(
                    $pdo,
                    $config["activePack"],
                    "no_world_data",
                    [
                        "{world}" => $world,
                    ],
                ),
            ]);
            exit();
        }

        // Alle Punktzahlen extrahieren, um Gleichstände zu prüfen
        $allPoints = array_column($todayLogs, "total_points");
        $pointCounts = array_count_values($allPoints);

        // --- 1. GEWINNER ERMITTELN ---
        $winnerData = $todayLogs[0];
        $winnerPoints = $winnerData["total_points"];
        $winnerPing = "<@{$winnerData["actor_id"]}>";

        // Prüfen, ob die höchste Punktzahl mehrfach vorkommt
        $winnerTie = $pointCounts[$winnerPoints] > 1;

        $outputMsg = getRandomSnatchText(
            $pdo,
            $config["activePack"],
            "daily_start",
            [
                "{world}" => $world,
            ],
        );

        if ($winnerTie) {
            $outputMsg .= getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "daily_winner_draw",
                [
                    "{winnerPoints}" => $winnerPoints,
                ],
            );
        } else {
            $outputMsg .= getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "daily_winner",
                [
                    "{winnerPing}" => $winnerPing,
                    "{winnerPoints}" => $winnerPoints,
                ],
            );

            // === NEU: KARTE FÜR DEN GEWINNER GENERIEREN & SPEICHERN ===
            // Eine gewichtete, zufällige Karte aus der DB generieren
            $rewardCard = generateWeightedCardFromDb($pdo, $config);

            // Prüfen, ob der Gewinner genau DIESE Karten-ID bereits besitzt
            $stmtCheckWinnerCard = $pdo->prepare(
                "SELECT 1 FROM snatch_cards WHERE user_id = ? AND card_id = ?",
            );
            $stmtCheckWinnerCard->execute([
                $winnerData["user_id"],
                $rewardCard["id"],
            ]);

            if ($stmtCheckWinnerCard->fetch()) {
                $outputMsg .= getRandomSnatchText(
                    $pdo,
                    $config["activePack"],
                    "daily_winner_same_card",
                    [
                        "{rewardCardemoji}" => $rewardCard["emoji"],
                        "{rewardCardname}" => $rewardCard["name"],
                    ],
                );
            } else {
                // Karte in die Datenbank des Gewinners eintragen
                $stmtInsertWinnerCard = $pdo->prepare("
                    INSERT INTO snatch_cards (user_id, card_id, card_name, emoji, category)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtInsertWinnerCard->execute([
                    $winnerData["user_id"],
                    $rewardCard["id"],
                    $rewardCard["name"],
                    $rewardCard["emoji"],
                    $rewardCard["category"],
                ]);

                $outputMsg .= getRandomSnatchText(
                    $pdo,
                    $config["activePack"],
                    "daily_winner_win_card",
                    [
                        "{rewardCardemoji}" => $rewardCard["emoji"],
                        "{rewardCardname}" => $rewardCard["name"],
                        "{rewardCardid}" => $rewardCard["id"],
                        "{rewardCardcategory}" => $rewardCard["category"],
                    ],
                );
            }
        }

        // --- 2. VERLIERER ERMITTELN ---
        if (count($todayLogs) > 1) {
            $loserData = end($todayLogs); // Letzter Eintrag (niedrigste Punkte)
            $loserPoints = $loserData["total_points"];
            $loserPing = "<@{$loserData["actor_id"]}>";

            // Prüfen, ob die niedrigste Punktzahl mehrfach vorkommt
            $loserTie = $pointCounts[$loserPoints] > 1;

            $outputMsg .= getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "daily_loser_start",
            );

            if ($loserTie) {
                $outputMsg .= getRandomSnatchText(
                    $pdo,
                    $config["activePack"],
                    "daily_loser_draw",
                    [
                        "{loserPoints}" => $loserPoints,
                    ],
                );
            } else {
                // Der Verlierer steht eindeutig fest -> Er verliert eine zufällige Karte aus seinem Besitz
                $stmtCards = $pdo->prepare(
                    "SELECT id, card_name, emoji FROM snatch_cards WHERE user_id = ?",
                );
                $stmtCards->execute([$loserData["user_id"]]);
                $loserCards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($loserCards)) {
                    // Eine zufällige Karte aus dem Besitz auswählen
                    $lostCard = $loserCards[array_rand($loserCards)];

                    // Karte aus der Datenbank löschen
                    $stmtDelete = $pdo->prepare(
                        "DELETE FROM snatch_cards WHERE id = ?",
                    );
                    $stmtDelete->execute([$lostCard["id"]]);

                    $outputMsg .= getRandomSnatchText(
                        $pdo,
                        $config["activePack"],
                        "daily_loser_lost_card",
                        [
                            "{loserPing}" => $loserPing,
                            "{loserPoints}" => $loserPoints,
                            "{lostCardemoji}" => $lostCard["emoji"],
                            "{lostCardname}" => $lostCard["card_name"],
                        ],
                    );
                } else {
                    $outputMsg .= getRandomSnatchText(
                        $pdo,
                        $config["activePack"],
                        "daily_loser_no_card",
                        [
                            "{loserPing}" => $loserPing,
                            "{loserPoints}" => $loserPoints,
                        ],
                    );
                }
            }
        } else {
            $outputMsg .= getRandomSnatchText(
                $pdo,
                $config["activePack"],
                "daily_no_game",
            );
        }

        echo json_encode([
            "text" => $outputMsg,
        ]);
        exit();
    } catch (PDOException $e) {
        error_log("Snatch-Winner-Fehler: " . $e->getMessage());
        echo json_encode([
            "text" => "❌ Fehler bei der Datenbankabfrage des Tagesgewinners.",
        ]);
        exit();
    }
}

// ==========================================================
// REGEL 5: THEME-CHECK & PAYLOAD PACKEN FOR SNATCH-GAME
// ==========================================================

// 1. WICHTIG: Wenn es sich um den Vorschau-Modus handelt, reichen wir diesen 1:1 durch
if (strtolower($theme) === "preview_mode") {
    $theme = "PREVIEW_MODE";
    $input["theme"] = "PREVIEW_MODE";
}
// Normaler Fallback, falls kein Theme mitgegeben wurde oder ein Speicherbefehl vorliegt
elseif (empty($theme) || str_starts_with(strtolower($theme), "set:")) {
    $theme = $dbUser["theme"] ?: "Gold";
    $input["theme"] = $theme;
}
// Normaler Theme-Wechsel/Check im Spiel
else {
    $lowerTheme = strtolower($theme);

    // Prüfe, ob es sich um ein dynamisches Sonder-Theme ODER eine Wunsch-Kommaliste handelt
    if (
        str_contains($lowerTheme, "kombo-theme") ||
        str_contains($lowerTheme, "zufall") ||
        str_contains($lowerTheme, ",") // <-- NEU: Kommalisten ebenfalls als dynamisch durchwinken!
    ) {
        // Wir behalten den originalen String exakt bei (z.B. "Barde,Warmage,Krark")
        $input["theme"] = $theme;
    } else {
        // Normales Einzel-Theme aus der Datenbank abfragen
        $stmt = $pdo->prepare(
            "SELECT theme_name FROM snatch_themes WHERE LOWER(theme_name) = ?",
        );
        $stmt->execute([$lowerTheme]);
        $dbThemeName = $stmt->fetchColumn();

        if ($dbThemeName) {
            $theme = $dbThemeName;
        } else {
            // Wenn es das Theme nicht gibt, nimm das gespeicherte User-Theme oder Gold
            $theme = $dbUser["theme"] ?: "Gold";
        }
        $input["theme"] = $theme;
    }
}

// 2. Sammlerkarten des Users für das Würfelspiel ins Array packen
if ($theme === "PREVIEW_MODE" && isset($input["ownedCards"])) {
    $formattedCards = [];
    foreach ($input["ownedCards"] as $card) {
        if (is_array($card) && isset($card["id"])) {
            $formattedCards[] = ["id" => (int) $card["id"]];
        } else {
            $formattedCards[] = ["id" => (int) $card];
        }
    }
    $input["ownedCards"] = $formattedCards;
} else {
    if (empty($input["ownedCards"])) {
        $stmt = $pdo->prepare(
            "SELECT card_id AS id, card_name AS name, emoji, category FROM snatch_cards WHERE user_id = ?",
        );
        $stmt->execute([$dbUser["id"]]);
        $input["ownedCards"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $formattedCards = [];
        foreach ($input["ownedCards"] as $card) {
            if (is_array($card) && isset($card["id"])) {
                $formattedCards[] = ["id" => (int) $card["id"]];
            } else {
                $formattedCards[] = ["id" => (int) $card];
            }
        }
        $input["ownedCards"] = $formattedCards;
    }
}

// Bereinigung vor dem Versand an das Spiel
if (str_starts_with(strtolower($theme), "set:")) {
    $theme = trim(substr($theme, 4));
    $input["theme"] = $theme;
}

// ==========================================================
// CURL-WEITERLEITUNG AN SNATCH-GAME.PHP
// ==========================================================
$gameUrl = str_replace(
    "shine-snatch.php",
    "snatch-game.php",
    $config["api_url"],
);
$ch = curl_init($gameUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// WICHTIG: Wir senden das aktualisierte $input-Array, in dem jetzt "theme" => "PREVIEW_MODE" steht!
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
$shineResponse = curl_exec($ch);
$responseArr = json_decode($shineResponse, true) ?? [];

// === HIER DIE ERWEITERUNG FÜR DAS WILLKOMMENS-GESCHENK ===
// Wir prüfen, ob im $dbUser (der oben bei $dbUser = getOrCreateUser(...) geladen wurde)
// ein Geschenk hinterlegt wurde.
if (!empty($dbUser["gift_card"])) {
    $card = $dbUser["gift_card"];

    // Wir fügen das Geschenk in das $responseArr ein, das an den Bot geht
    $responseArr["gift"] = [
        "message" => getRandomSnatchText(
            $pdo,
            $config["activePack"],
            "new_player_gift",
        ),
        "card_name" => $card["name"],
        "card_emoji" => $card["emoji"],
        "card_category" => $card["category"],
    ];
}

// ==========================================================
// LIVE DETAILED LOGGING (Datei-Backup UND DB-Insert)
// ==========================================================
$world = $input["world"] ?? "Unbekannt";
//$excludedWorlds = ["keine"];
$isSecretPull = false;
$publicHtml = null;

$excludedWorlds = [
    "Theme-Editor",
    "Dashboard-Admin",
    "Dashboard",
    "Dashboard-EyeCatcher",
    "Test-System",
    "Vorschau",
    "Test-Umgebung",
];

// Prüfe explizit, ob wir Punkte haben (Spieler hat eine Aktion durchgeführt)
if (!in_array($world, $excludedWorlds) && isset($responseArr["total_points"])) {
    // IP-Adresse ermitteln
    $remoteIp =
        $_SERVER["HTTP_X_FORWARDED_FOR"] ??
        ($_SERVER["REMOTE_ADDR"] ?? "UNKNOWN");
    if (strpos($remoteIp, ",") !== false) {
        $remoteIp = explode(",", $remoteIp)[0];
    }
    $remoteIp = trim($remoteIp);

    // IP-Anonymisierung: Nur den letzten Block zu xxx machen
    $anonIp = "UNKNOWN";
    if ($remoteIp !== "UNKNOWN") {
        if (filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $anonIp = preg_replace('/[0-9]+$/', "xxx", $remoteIp);
        } elseif (filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(":", $remoteIp);
            if (count($parts) > 1) {
                $parts[count($parts) - 1] = "xxxx";
                $anonIp = implode(":", $parts);
            } else {
                $anonIp = "xxxx:xxxx:xxxx:xxxx";
            }
        }
    }

    $serverId = !empty($input["serverId"])
        ? trim((string) $input["serverId"])
        : "";

    $points = (int) ($responseArr["total_points"] ?? 0);
    $handIds = isset($responseArr["hand_ids"])
        ? (is_array($responseArr["hand_ids"])
            ? implode(",", $responseArr["hand_ids"])
            : $responseArr["hand_ids"])
        : "";
    $ownedIds = !empty($input["ownedCards"])
        ? implode(",", array_column($input["ownedCards"], "id"))
        : "";
    $chanName = $input["version"] ?? "Foundry-Chat";
    $reqUrl = $input["url"] ?? "Keine-URL";
    $playername = !empty($input["playerName"])
        ? trim((string) $input["playerName"])
        : "";

    // NEU: Sicherstellen, dass ein gültiger String für das Theme vorliegt
    // Falls das ermittelte Theme ein Array aus deiner Config-Funktion ist, nimm den 'key'
    $logTheme = "Gold";
    if (isset($theme)) {
        $logTheme = is_array($theme) ? $theme["key"] ?? "Gold" : $theme;
    }

    // Jede der 10 Seiten hat hier ihr eigenes, fest eingetragenes Token
    $myWebseiteToken =
        "009f3c9aebc0aba26d77f6ef8a252f91c5ff83c717ae97e8b811ade4874f754d";
    $text = "🃏 Snatch auf $chanName \n$playername: $points";
    // Funktion aufrufen – keine DB nötig!
    $gesendet = sendTelegramViaApi($myWebseiteToken, $text);

    try {
        // Spalte 'theme' im SQL-Query hinzugefügt
        $sql = "INSERT INTO snatch_logs (ip_address, server_id, server_name, channel_name, url, user_id, total_points, pulled_cards, owned_cards, theme)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtLog = $pdo->prepare($sql);
        $stmtLog->execute([
            $anonIp,
            $serverId,
            $world,
            $chanName,
            $reqUrl,
            (int) $dbUser["id"],
            $points,
            (string) $handIds,
            (string) $ownedIds,
            (string) $logTheme, // <-- Hier wird der Theme-String übergeben
        ]);
    } catch (PDOException $e) {
        error_log("Snatch-Log-Fehler: " . $e->getMessage());
    }

    // =========================================================================
    // --- GEHEIMER ERSTER ZUG DES TAGES LOGIK (HIER INNERHALB DES SPIELS) ---
    // =========================================================================

    // --- THEME ERMITTLUNG GEMￃﾄSS DEINER REGELN ---
    // --- THEME ERMITTLUNG GEMÄSS DEINER REGELN ---
    $userThemeInput = trim($input["theme"] ?? ""); // Was vom Bot/User übergeben wurde (z.B. "Barde", "Zufall", "Alchemie,Barde")

    // 1. Theme-Namen parsen
    $selectedThemeName = "gold"; // Absoluter Fallback

    if (empty($userThemeInput) || strtolower($userThemeInput) === "default") {
        // Wenn keins angegeben ist: Server-Default aus snatch_settings holen
        $stmtThemeSet = $pdo->prepare(
            "SELECT setting_value FROM snatch_settings WHERE server_id = ? AND setting_key = 'default_theme'",
        );
        $stmtThemeSet->execute([$serverId]);
        $selectedThemeName = $stmtThemeSet->fetchColumn();

        // Wenn für den Server keins gesetzt ist, dann ID 0 holen
        if (!$selectedThemeName) {
            $stmtThemeSet->execute(["0"]);
            $selectedThemeName = $stmtThemeSet->fetchColumn() ?: "gold";
        }
    } elseif (
        strtolower($userThemeInput) === "zufall" ||
        strtolower($userThemeInput) === "kombo-theme"
    ) {
        // Bei Zufall oder Kombo-Theme: Ein völlig zufälliges aus der DB holen
        $stmtRand = $pdo->query(
            "SELECT theme_name FROM snatch_themes ORDER BY RAND() LIMIT 1",
        );
        $selectedThemeName = $stmtRand->fetchColumn() ?: "gold";
    } else {
        // Es wurden spezifische Themes übergeben (kommagetrennt möglich)
        $themesArray = array_map("trim", explode(",", $userThemeInput));
        if (count($themesArray) > 1) {
            // Wenn mehrere angegeben sind, ein zufälliges von diesen wählen
            $selectedThemeName = $themesArray[array_rand($themesArray)];
        } else {
            // Wenn eins angegeben ist, genau das nehmen
            $selectedThemeName = $themesArray[0];
        }
    }

    // Jetzt die echten Farben und Labels aus snatch_themes ziehen
    $stmtGetTheme = $pdo->prepare(
        "SELECT * FROM snatch_themes WHERE theme_name = ?",
    );
    $stmtGetTheme->execute([$selectedThemeName]);
    $cfg = $stmtGetTheme->fetch(PDO::FETCH_ASSOC);

    // Fallback, falls das gewählte Theme nicht in der DB existiert
    if (!$cfg) {
        $stmtGetTheme->execute(["Gold"]); // Alchemie oder dein Standard als Ausweichdaten
        $cfg = $stmtGetTheme->fetch(PDO::FETCH_ASSOC);
    }

    // --- ZENTRALE STYLE-VARIABLEN ---
    $s_container = "display: block; box-sizing: border-box; width: 100%; font-family: 'Signika', sans-serif; border: 2px solid {$cfg["color_accent"]}; border-radius: 10px; background-color: {$cfg["color_bg"]}; padding: 12px; color: {$cfg["color_text_main"]}; box-shadow: 0 6px 12px {$cfg["shadow_color"]};";
    $s_header = "border-bottom: 2px solid {$cfg["color_primary"]}; margin-top: 0; text-align: center; color: {$cfg["color_bolt_core"]}; text-transform: uppercase;";
    $s_header_title = "font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";
    $s_section_label = "margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_accent"]};";
    $s_card_list = "list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid {$cfg["color_primary"]}; border-radius: 4px; background-color: {$cfg["color_bg_card"]};";
    $s_card_item =
        "border-bottom: 1px solid #333; padding: 2px 0; list-style: none;";
    $s_bold_bolt = "color: {$cfg["color_bolt_core"]};";
    $s_subtotal_block = "text-align: right; font-size: 0.9em; color: {$cfg["color_text_muted"]}; border-top: 1px solid rgba(255,255,255,0.1); margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;";
    $s_combo_container = "padding: 8px; background-color: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid {$cfg["color_primary"]};";
    $s_unused_wrapper = "margin-top: 10px; opacity: 0.7;";
    $s_unused_container = "padding: 4px 8px; border-left: 2px solid {$cfg["color_text_muted"]};";
    $s_unused_item = "color: {$cfg["color_text_muted"]}; font-size: 0.9em; margin-bottom: 1px;";
    $s_total_box = "text-align: center; font-size: 1.4rem; margin-top: 15px; padding: 12px; background-color: {$cfg["color_bg"]}; color: {$cfg["color_bolt_core"]}; border: 1px solid {$cfg["color_accent"]}; border-radius: 6px; font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";
    $s_bonus_box =
        "padding: 5px; background-color: " .
        ($cfg["color_special_bg"] ?? "rgba(74, 222, 128, 0.1)") .
        "; border: 1px solid {$cfg["color_primary"]}; border-radius: 4px; margin-bottom: 10px; font-size: 0.9em;";
    $s_bonus_title = "color: {$cfg["color_primary"]}; font-weight: bold;";
    $s_bonus_pts = "float: right; color: {$cfg["color_bolt_core"]}; font-weight: bold;";
    $s_bonus_detail = "font-size: 0.9em; color: {$cfg["color_text_main"]}; opacity: 0.8;";
    // --- ZENTRALE STYLE-VARIABLEN ---
    $s_container = "display: block; box-sizing: border-box; width: 100%; font-family: 'Signika', sans-serif; border: 2px solid {$cfg["color_accent"]}; border-radius: 10px; background-color: {$cfg["color_bg"]}; padding: 12px; color: {$cfg["color_text_main"]}; box-shadow: 0 6px 12px {$cfg["shadow_color"]};";
    $s_header = "border-bottom: 2px solid {$cfg["color_primary"]}; margin-top: 0; text-align: center; color: {$cfg["color_bolt_core"]}; text-transform: uppercase;";
    $s_header_title = "font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";
    $s_section_label = "margin: 8px 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_accent"]};";
    $s_card_list = "list-style: none; padding: 8px; margin-bottom: 5px; border: 1px solid {$cfg["color_primary"]}; border-radius: 4px; background-color: {$cfg["color_bg_card"]};";
    $s_card_item =
        "border-bottom: 1px solid #333; padding: 2px 0; list-style: none;";
    $s_bold_bolt = "color: {$cfg["color_bolt_core"]};";
    $s_subtotal_block = "text-align: right; font-size: 0.9em; color: {$cfg["color_text_muted"]}; border-top: 1px solid rgba(255,255,255,0.1); margin-bottom: 12px; padding: 5px 5px 0 0; font-style: italic;";
    $s_combo_container = "padding: 8px; background-color: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid {$cfg["color_primary"]};";
    $s_unused_wrapper = "margin-top: 10px; opacity: 0.7;";
    $s_unused_container = "padding: 4px 8px; border-left: 2px solid {$cfg["color_text_muted"]};";
    $s_unused_item = "color: {$cfg["color_text_muted"]}; font-size: 0.9em; margin-bottom: 1px;";
    $s_total_box = "text-align: center; font-size: 1.4rem; margin-top: 15px; padding: 12px; background-color: {$cfg["color_bg"]}; color: {$cfg["color_bolt_core"]}; border: 1px solid {$cfg["color_accent"]}; border-radius: 6px; font-weight: bold; text-shadow: 0 0 10px {$cfg["color_primary"]}, 0 0 20px {$cfg["color_primary"]};";

    // Sicherstellen, dass das activePack aus der Config geladen wird
    $activePack = isset($config["activePack"])
        ? $config["activePack"]
        : "default";

    // Prüfen, ob das Feature für diesen Server aktiv ist
    $publicHtml = "";
    $isSecretPull = false;

    if (!empty($serverId)) {
        $stmtSet = $pdo->prepare(
            "SELECT setting_value FROM snatch_settings WHERE server_id = ? AND setting_key = 'secret_first_pull'",
        );
        $stmtSet->execute([$serverId]);
        $settingActive = $stmtSet->fetchColumn();

        // Wenn das Feature aktiv ist
        if ($settingActive === "1") {
            // Prüfen, ob der User heute bereits einen Log-Eintrag hat
            $todayStart = date("Y-m-d 00:00:00");
            $stmtCheckLog = $pdo->prepare(
                "SELECT COUNT(*) FROM snatch_logs WHERE user_id = ? AND server_id = ? AND created_at >= ?",
            );
            $stmtCheckLog->execute([
                (int) $dbUser["id"],
                $serverId,
                $todayStart,
            ]);
            $pullsToday = $stmtCheckLog->fetchColumn();

            // Da der aktuelle Log-Eintrag gerade eben oben geschrieben wurde, ist der Counter bei genau 1
            if ($pullsToday == 1) {
                //if ($pullsToday <= 100) {
                $isSecretPull = true;

                // Wir holen einen passenden Text aus dem Pool für die Ankündigung
                $announcementText = getRandomSnatchText(
                    $pdo,
                    $activePack,
                    "daily_first_draw_secret",
                    [
                        "{playerName}" => $playername,
                    ],
                );

                // --- WÜRFEL-CHANCEN FÜR DIE DISCORD/FOUNDRY TEASER ---
                $showCards = rand(1, 100) <= 20; // 20% Chance
                $showCombos = rand(1, 100) <= 50; // 50% Chance

                // Diese gemeinsame Variable sammelt alle gewürfelten Inhalte für das finale Template
                $teaserSectionsHtml = "";

                // 1. Teaser-Abschnitt: Sammelkarten Spruch (20% Chance)
                if ($showCards) {
                    $teaserSectionsHtml .= "
                        <div style=\"$s_bonus_box\" data-edit-keys='colorPrimary,colorSpecialBg'>
                            <span style=\"$s_bonus_title\" data-edit-keys='colorPrimary'>{$cfg["label_special_bonus"]}</span>
                            <span style=\"$s_bonus_pts\" data-edit-keys='colorBoltCore'>+?? Pkt</span>
                            <div style=\"$s_bonus_detail\" data-edit-keys='colorTextMain'>Gewürfelt: Sammelkarten Bonus ??
                        </div>
                        </div>
                        <div style=\"$s_subtotal_block\" data-edit-keys='colorTextMain'>
                            {$cfg["label_sub_total"]} <strong style=\"$s_bold_bolt\" data-edit-keys='colorBoltCore'>?? Pkt</strong>
                        </div>";
                }

                // 2. Teaser-Abschnitt: Synergien & Verfallene Pfade (50% Chance)
                if ($showCombos) {
                    // Teil A: Aktive Kombinationen Andeutung
                    $teaserSectionsHtml .= "
                        <div style=\"margin-bottom: 10px;\">
                            <p style=\"margin: 0 0 4px 0; font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_accent"]};\" data-edit-keys='colorAccent'>{$cfg["label_combos"]}:</p>
                            <div style=\"$s_combo_container\" data-edit-keys='colorPrimary'>
                                <i style=\"color: {$cfg["color_text_muted"]}; font-style: italic;\" data-edit-keys='colorTextMuted'>Es sieht so aus, als hättest du eine Synergie... oder?</i>
                            </div>
                        </div>";

                    // Teil B: Verfallene Pfade (Private Message Hinweis)
                    $teaserSectionsHtml .= "
                        <div style=\"$s_unused_wrapper\">
                            <p style=\"margin: 0 0 2px 0; font-size: 0.9em; font-weight: bold; text-transform: uppercase; color: {$cfg["color_text_muted"]};\" data-edit-keys='colorTextMuted'>{$cfg["label_unused"]}:</p>
                            <div style=\"$s_unused_container\" data-edit-keys='colorTextMuted'>
                                <div style=\"$s_unused_item\" data-edit-keys='colorTextMuted'>
                                    ℹ️ <i>Du hast eine Message erhalten.</i>
                                </div>
                            </div>
                        </div>";
                }

                // --- GENERIERUNG DES FINALEN ÖFFENTLICHEN HTML-BANNERS ---
                $publicHtml = "
                    <div style=\"$s_container\" data-edit-key='shadowColor,colorTextMain,colorBg,colorAccent'>

                        <h2 style=\"$s_header\" data-edit-keys='colorPrimary,colorBoltCore'>
                            <span style=\"$s_header_title\" data-edit-keys='colorPrimary'>
                                {$cfg["header_icon"]} {$cfg["header_title"]}
                            </span>
                        </h2>

                        <p style=\"$s_section_label\" data-edit-keys='colorAccent'>
                        {$cfg["label_hand"]}
                        </p>

                        <div style=\"padding: 20px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; background-color: {$cfg["color_bg_card"]}; text-align: center; border-left: 4px solid {$cfg["color_primary"]};\">
                            <div style=\"font-size: 3.5rem; margin-bottom: 10px; animation: secretPulse 2s infinite ease-in-out;\">🔒📦</div>
                            <p style=\"margin: 5px 0; font-size: 1.05em; font-weight: 500; color: {$cfg["color_text_main"]}; text-shadow: 0 1px 3px #000;\">
                                {$announcementText}
                            </p>
                        </div>

                        $teaserSectionsHtml

                        <div style=\"$s_total_box\" data-edit-keys='colorBg,colorBoltCore,colorAccent,colorPrimary'>
                            {$cfg["label_total"]} GEHEIM
                        </div>

                    </div>
                    <style>
                        @keyframes secretPulse {
                            0% { transform: scale(1); filter: drop-shadow(0 0 2px transparent); }
                            50% { transform: scale(1.08); filter: drop-shadow(0 0 8px {$cfg["color_primary"]}); }
                            100% { transform: scale(1); filter: drop-shadow(0 0 2px transparent); }
                        }
                    </style>";
            }
        }
    }
    // =========================================================================
}

// System-Nachrichten mergen
if (isset($msg) && isset($responseArr["text"])) {
    $responseArr["text"] = $msg . "\n" . $responseArr["text"];
}

// --- DEBUG-BLOCK: Prￃﾼfen ob das Spiel antwortet ---
if (empty($shineResponse)) {
    echo json_encode([
        "error" => "Keine Antwort von snatch-game.php erhalten. URL prￃﾼfen!",
    ]);
    exit();
}

if (empty($responseArr)) {
    echo json_encode([
        "error" => "Keine Antwort-Daten (responseArr) vorhanden!",
    ]);
    exit();
}

// FINALE JSON-AUSGABE
echo json_encode([
    "status" => "success",
    "secret" => $isSecretPull,
    "html" => isset($shineResponse["html"])
        ? $shineResponse["html"]
        : (isset($responseArr["html"])
            ? $responseArr["html"]
            : ""),
    "public_html" => $publicHtml,
    "text" => isset($responseArr["text"]) ? $responseArr["text"] : "",
]);
exit();
