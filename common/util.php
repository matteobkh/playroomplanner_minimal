<?php
/**
 * util.php - Funzioni di utilità condivise
 */

require_once __DIR__ . '/config.php';

/* === AUTENTICAZIONE === */

// Restituisce utente loggato o null
function getLoggedUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// Verifica login, altrimenti errore 401
function requireLogin(): array {
    $user = getLoggedUser();
    if (!$user) jsonError('Accesso non autorizzato', 401);
    return $user;
}

// Verifica se utente è responsabile (ha data_inizio_responsabile)
function isResponsabile(?array $user): bool {
    return $user && $user['ruolo'] === 'responsabile' && $user['data_inizio_responsabile'];
}

// Richiede ruolo responsabile, altrimenti errore 403
function requireResponsabile(): array {
    $user = requireLogin();
    if (!isResponsabile($user)) jsonError('Riservato ai responsabili', 403);
    return $user;
}

/* === RISPOSTE JSON === */

// Risposta JSON successo
function jsonSuccess($data = null): void {
    header('Content-Type: application/json');
    die(json_encode(['ok' => true, 'data' => $data]));
}

// Risposta JSON errore
function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    die(json_encode(['ok' => false, 'error' => $msg]));
}

/* === VALIDAZIONI PRENOTAZIONI === */

// Verifica orario: ore intere, tra 9:00 e 23:00
function validaOrario(string $dataOra): bool {
    $dt = new DateTime($dataOra);
    return $dt->format('i') === '00' && $dt->format('H') >= 9 && $dt->format('H') <= 23;
}

// Verifica sovrapposizione sala (esclude prenotazione corrente se specificata)
function sovrapposizioneSala(int $salaId, string $inizio, int $durata, ?int $excludeId = null): bool {
    $fine = (new DateTime($inizio))->modify("+$durata hours")->format('Y-m-d H:i:s');
    $sql = "SELECT 1 FROM prenotazione WHERE sala_id = ? AND data_ora_inizio < ? AND DATE_ADD(data_ora_inizio, INTERVAL durata HOUR) > ?";
    $params = [$salaId, $fine, $inizio];
    if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

// Verifica sovrapposizione impegni utente
function sovrapposizioneUtente(string $email, string $inizio, int $durata, ?int $excludeId = null): bool {
    $fine = (new DateTime($inizio))->modify("+$durata hours")->format('Y-m-d H:i:s');
    $sql = "SELECT 1 FROM invito i JOIN prenotazione p ON i.prenotazione_id = p.id 
            WHERE i.iscritto_email = ? AND i.risposta = 'si' AND p.data_ora_inizio < ? 
            AND DATE_ADD(p.data_ora_inizio, INTERVAL p.durata HOUR) > ?";
    $params = [$email, $fine, $inizio];
    if ($excludeId) { $sql .= " AND p.id != ?"; $params[] = $excludeId; }
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

// Conta partecipanti confermati
function contaPartecipanti(int $id): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM invito WHERE prenotazione_id = ? AND risposta = 'si'");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn();
}

// Ottiene capienza sala
function getCapienza(int $salaId): int {
    $stmt = getDB()->prepare("SELECT capienza FROM sala WHERE id = ?");
    $stmt->execute([$salaId]);
    return (int)$stmt->fetchColumn();
}

// Ottiene prenotazione per ID
function getPrenotazione(int $id): ?array {
    $stmt = getDB()->prepare("SELECT * FROM prenotazione WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Calcola lunedì della settimana
function getLunedi(string $data): string {
    $dt = new DateTime($data);
    $dt->modify('monday this week');
    return $dt->format('Y-m-d');
}

// Sanitizza input
function sanitize($v): string {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}
