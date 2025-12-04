<?php
/**
 * config.php - Configurazione database e sessione
 */

session_start();

// Parametri connessione database
define('DB_HOST', 'localhost');
define('DB_NAME', 'playroomplanner');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connessione PDO singleton
function getDB(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
