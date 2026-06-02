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
        "text" =>
            "🧙‍♂️ **Snatch-Erschaffung!** Der Artefakt-Schmied {$actorPing} lässt aus dem Nichts eine Karte für {$targetPing} erscheinen!\n" .
            "Erhalten: {$generatedCard["emoji"]} **{$generatedCard["name"]}** (#{$generatedCard["id"]} | *{$generatedCard["category"]}*)!",
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
            "text" =>
                "❌ Du hast keine Berechtigung, diesen Befehl auszuführen!",
        ]);
        exit();
    }

    $world = $input["world"] ?? "Unbekannt";

    if (empty($world) || $world === "Unbekannt") {
        echo json_encode([
            "text" =>
                "❌ Keine gültigen Serverdaten (World) für die Auswertung vorhanden.",
        ]);
        exit();
    }

    try {
        // ANTI-CHEAT-ABFRAGE:
        // Holt für jeden User ausschließlich das ALLERERSTE Spiel des heutigen Tages (MIN(l.id))
        // und sortiert die Liste nach den Punkten absteigend.
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
                "text" => "📅 **Tages-Event:** Für den Server **{$world}** wurden heute noch keine gültigen Event-Spiele aufgezeichnet.",
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

        $outputMsg = "🏆 **Tages-Event Auswertung für {$world}** 🏆\n\n";

        if ($winnerTie) {
            $outputMsg .= "🤝 **Unentschieden an der Spitze!** Mehrere Spieler haben Gleichstand mit **{$winnerPoints} Punkten** erreicht. Daher gewinnt heute niemand eine Bonuskarte!\n\n";
        } else {
            $outputMsg .=
                "Der heutige Champion ist {$winnerPing} mit fantastischen **{$winnerPoints} Punkten**! 🎉\n" .
                "*(Gewertet wurde fairerweise nur das jeweils erste Spiel des Tages jedes Teilnehmers!)*\n";

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
                $outputMsg .= "✨ **Kartenglück im Unglück:** Der Champion hätte die Karte {$rewardCard["emoji"]} **{$rewardCard["name"]}** gewonnen, besaß diese aber bereits. Das Album bleibt unverändert! 🃏\n\n";
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

                $outputMsg .= "🎁 **Siegesprämie erhalten:** Als Belohnung materialisiert sich die Karte {$rewardCard["emoji"]} **{$rewardCard["name"]}** (#{$rewardCard["id"]} | *{$rewardCard["category"]}*) in deinem Album!\n\n";
            }
        }

        // --- 2. VERLIERER ERMITTELN ---
        if (count($todayLogs) > 1) {
            $loserData = end($todayLogs); // Letzter Eintrag (niedrigste Punkte)
            $loserPoints = $loserData["total_points"];
            $loserPing = "<@{$loserData["actor_id"]}>";

            // Prüfen, ob die niedrigste Punktzahl mehrfach vorkommt
            $loserTie = $pointCounts[$loserPoints] > 1;

            $outputMsg .= "📉 **Der Trostpreis des Tages:**\n";

            if ($loserTie) {
                $outputMsg .= "Kopf hoch! Es gibt einen Gleichstand am Tabellenende bei **{$loserPoints} Punkten**. Glück im Unglück: **Keiner verliert eine Karte!** 💖";
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

                    $outputMsg .=
                        "Oje {$loserPing}... Mit nur **{$loserPoints} Punkten** lief es heute gar nicht gut. " .
                        "Die Würfelgötter sind erzürnt: Du verlierst die Karte {$lostCard["emoji"]} **{$lostCard["name"]}** aus deinem Album! 🧼💔";
                } else {
                    $outputMsg .=
                        "Kopf hoch {$loserPing}! Mit nur **{$loserPoints} Punkten** hast du zwar den letzten Platz belegt, " .
                        "aber da dein Album noch komplett leer ist, konntest du keine Karte verlieren! 🕳️✨";
                }
            }
        } else {
            $outputMsg .=
                "ℹ️ *Es gab heute keine weiteren Teilnehmer für die Verlierer-Wertung.*";
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
        "message" =>
            "✨ **Willkommensgeschenk!** Du hast deine erste Karte erhalten:",
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

    // NEU: Sicherstellen, dass ein gültiger String für das Theme vorliegt
    // Falls das ermittelte Theme ein Array aus deiner Config-Funktion ist, nimm den 'key'
    $logTheme = "Gold";
    if (isset($theme)) {
        $logTheme = is_array($theme) ? $theme["key"] ?? "Gold" : $theme;
    }

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
            (string) $logTheme, // <-- NEU: Hier wird der Theme-String übergeben
        ]);
    } catch (PDOException $e) {
        error_log("Snatch-Log-Fehler: " . $e->getMessage());
    }
}

// System-Nachrichten mergen
if (isset($msg) && isset($responseArr["text"])) {
    $responseArr["text"] = $msg . "\n" . $responseArr["text"];
}

// --- DEBUG-BLOCK: Prüfen ob das Spiel antwortet ---
if (empty($shineResponse)) {
    echo json_encode([
        "error" => "Keine Antwort von snatch-game.php erhalten. URL prüfen!",
    ]);
    exit();
}

if (empty($responseArr)) {
    echo json_encode([
        "error" => "JSON-Fehler: snatch-game.php lieferte kein gültiges JSON.",
        "raw" => $shineResponse,
    ]);
    exit();
}

// Finale Ausgabe an die Foundry-Anwendung / den Bot senden
echo json_encode($responseArr);
