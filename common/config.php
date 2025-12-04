<?php
/**
 * config.php - Configurazione database e sessione
 * Contiene i parametri di connessione e avvia la sessione
 */

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'playroomplanner');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Restituisce la connessione PDO al database
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die(json_encode(['ok' => false, 'error' => 'Connessione database fallita']));
        }
    }
    return $pdo;
}
