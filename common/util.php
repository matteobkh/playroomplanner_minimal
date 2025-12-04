<?php
/**
 * util.php - Funzioni di utilità condivise
 * Dipendenze: config.php (per getDB)
 */

require_once __DIR__ . '/config.php';

/* ==================== AUTENTICAZIONE ==================== */

/**
 * Restituisce l'utente loggato o null
 */
function getLoggedUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Verifica se l'utente è loggato, altrimenti restituisce errore JSON
 */
function requireLogin(): array {
    $user = getLoggedUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Accesso non autorizzato']);
        exit;
    }
    return $user;
}

/**
 * Verifica se l'utente è un responsabile (ha data_inizio_responsabile non null)
 */
function isResponsabile(?array $user): bool {
    return $user && $user['ruolo'] === 'responsabile' && !empty($user['data_inizio_responsabile']);
}

/**
 * Richiede che l'utente sia un responsabile
 */
function requireResponsabile(): array {
    $user = requireLogin();
    if (!isResponsabile($user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo i responsabili possono eseguire questa operazione']);
        exit;
    }
    return $user;
}

/* ==================== RISPOSTE JSON ==================== */

/**
 * Risposta JSON standard di successo
 */
function jsonSuccess($data = null): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

/**
 * Risposta JSON standard di errore
 */
function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

/* ==================== VALIDAZIONI ==================== */

/**
 * Valida che l'orario di prenotazione sia ad ore intere tra 09:00 e 23:00
 */
function validaOrarioPrenotazione(string $dataOraInizio): bool {
    $dt = new DateTime($dataOraInizio);
    $ora = (int)$dt->format('H');
    $minuti = (int)$dt->format('i');
    return $minuti === 0 && $ora >= 9 && $ora <= 23;
}

/**
 * Verifica sovrapposizione prenotazioni nella stessa sala
 */
function esisteSovrapposizioneSala(int $salaId, string $dataOraInizio, int $durata, ?int $excludeId = null): bool {
    $pdo = getDB();
    $fine = (new DateTime($dataOraInizio))->modify("+{$durata} hours")->format('Y-m-d H:i:s');
    
    $sql = "SELECT COUNT(*) FROM prenotazione 
            WHERE sala_id = ? 
            AND data_ora_inizio < ? 
            AND DATE_ADD(data_ora_inizio, INTERVAL durata HOUR) > ?";
    $params = [$salaId, $fine, $dataOraInizio];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

/**
 * Verifica sovrapposizione impegni per un utente
 */
function esisteSovrapposizioneUtente(string $email, string $dataOraInizio, int $durata, ?int $excludePrenotazioneId = null): bool {
    $pdo = getDB();
    $fine = (new DateTime($dataOraInizio))->modify("+{$durata} hours")->format('Y-m-d H:i:s');
    
    $sql = "SELECT COUNT(*) FROM invito i
            JOIN prenotazione p ON i.prenotazione_id = p.id
            WHERE i.iscritto_email = ? 
            AND i.risposta = 'si'
            AND p.data_ora_inizio < ?
            AND DATE_ADD(p.data_ora_inizio, INTERVAL p.durata HOUR) > ?";
    $params = [$email, $fine, $dataOraInizio];
    
    if ($excludePrenotazioneId) {
        $sql .= " AND p.id != ?";
        $params[] = $excludePrenotazioneId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

/**
 * Conta i partecipanti confermati per una prenotazione
 */
function contaPartecipanti(int $prenotazioneId): int {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invito WHERE prenotazione_id = ? AND risposta = 'si'");
    $stmt->execute([$prenotazioneId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Ottiene la capienza di una sala
 */
function getCapienzaSala(int $salaId): int {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT capienza FROM sala WHERE id = ?");
    $stmt->execute([$salaId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Ottiene i dettagli di una prenotazione
 */
function getPrenotazione(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM prenotazione WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Ottiene il lunedì della settimana che contiene la data specificata
 */
function getLunediSettimana(string $data): string {
    $dt = new DateTime($data);
    $dayOfWeek = (int)$dt->format('N');
    $dt->modify('-' . ($dayOfWeek - 1) . ' days');
    return $dt->format('Y-m-d');
}

/**
 * Input sanitization
 */
function sanitize($value): string {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}
