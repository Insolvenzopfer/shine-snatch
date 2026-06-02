<?php
// db.php - Zentrale Datenbankverbindung mit automatischem .env-Fallback

function getDatabaseConnection()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // --- AUTOMATISCHER .ENV FALLBACK FÜR TESTSYSTEME ---
    $envPath = __DIR__ . "/../../.env";
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Kommentare überspringen
            if (str_starts_with(trim($line), "#")) {
                continue;
            }

            // Nur Zeilen mit einem Gleichheitszeichen verarbeiten
            if (strpos($line, "=") !== false) {
                [$key, $value] = explode("=", $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Anführungszeichen entfernen, falls vorhanden (z.B. DB_PASS="geheim")
                $value = trim($value, '"\'');

                // Nur setzen, wenn die Variable im Environment noch nicht existiert
                if (
                    getenv($key) === false &&
                    !isset($_ENV[$key]) &&
                    !isset($_SERVER[$key])
                ) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    // --- VARIABLEN AUSLESEN ---
    $dbHost =
        getenv("DB_HOST") !== false
            ? getenv("DB_HOST")
            : $_ENV["DB_HOST"] ?? ($_SERVER["DB_HOST"] ?? null);
    $dbPort =
        getenv("DB_PORT") !== false
            ? getenv("DB_PORT")
            : $_ENV["DB_PORT"] ?? ($_SERVER["DB_PORT"] ?? "3306");
    $dbName =
        getenv("DB_NAME") !== false
            ? getenv("DB_NAME")
            : $_ENV["DB_NAME"] ?? ($_SERVER["DB_NAME"] ?? null);
    $dbUser =
        getenv("DB_USER") !== false
            ? getenv("DB_USER")
            : $_ENV["DB_USER"] ?? ($_SERVER["DB_USER"] ?? null);
    $dbPass =
        getenv("DB_PASS") !== false
            ? getenv("DB_PASS")
            : $_ENV["DB_PASS"] ?? ($_SERVER["DB_PASS"] ?? "");

    // --- 3. NEU: CONFIG_NETCUP.PHP FALLBACK FÜR BACKUPSYSTEME ---
    // Falls die Pflichtvariablen immer noch leer sind, schauen wir, ob die PHP-Konfigurationsdatei existiert
    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $netcupConfigPath = __DIR__ . "config_netcup.php";

        if (file_exists($netcupConfigPath)) {
            $netcupConfig = require $netcupConfigPath;

            if (is_array($netcupConfig)) {
                $dbHost = $netcupConfig["DB_HOST"] ?? $dbHost;
                $dbName = $netcupConfig["DB_NAME"] ?? $dbName;
                $dbUser = $netcupConfig["DB_USER"] ?? $dbUser;
                $dbPass = $netcupConfig["DB_PASS"] ?? $dbPass;
                $dbPort = $netcupConfig["DB_PORT"] ?? $dbPort; // Falls du später mal einen Port in der PHP-Datei definierst
            }
        }
    }

    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "status" => "error",
            "message" =>
                "Datenbank-Umgebungsvariablen wurden weder im System noch in der .env gefunden!",
        ]);
        exit();
    }

    try {
        $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdo;
    } catch (PDOException $e) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "status" => "error",
            "message" =>
                "Datenbankverbindung fehlgeschlagen: " . $e->getMessage(),
        ]);
        exit();
    }
}
