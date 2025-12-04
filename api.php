<?php
/**
 * api.php - API centralizzata per Play Room Planner
 * Gestisce tutte le operazioni tramite routing interno
 * Dipendenze: common/util.php
 */

require_once __DIR__ . '/common/util.php';

header('Content-Type: application/json');

// Routing basato su metodo e action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        
        /* ==================== AUTH ==================== */
        
        case 'login':
            if ($method !== 'POST') jsonError('Metodo non consentito', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $email = sanitize($data['email'] ?? '');
            $password = $data['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                jsonError('Email e password richiesti');
            }
            
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM iscritto WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $password !== $user['password']) {
                jsonError('Credenziali non valide', 401);
            }
            
            unset($user['password']);
            $_SESSION['user'] = $user;
            jsonSuccess($user);
            break;
            
        case 'register':
            if ($method !== 'POST') jsonError('Metodo non consentito', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $email = sanitize($data['email'] ?? '');
            $nome = sanitize($data['nome'] ?? '');
            $cognome = sanitize($data['cognome'] ?? '');
            $password = $data['password'] ?? '';
            $dataNascita = $data['data_nascita'] ?? '';
            $ruolo = $data['ruolo'] ?? 'allievo';
            
            if (empty($email) || empty($nome) || empty($cognome) || empty($password) || empty($dataNascita)) {
                jsonError('Tutti i campi sono obbligatori');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonError('Email non valida');
            }
            
            if (!in_array($ruolo, ['docente', 'allievo', 'tecnico', 'responsabile'])) {
                jsonError('Ruolo non valido');
            }
            
            $dataInizioResp = ($ruolo === 'responsabile') ? date('Y-m-d') : null;
            
            $pdo = getDB();
            
            // Verifica email esistente
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM iscritto WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                jsonError('Email già registrata');
            }
            
            $stmt = $pdo->prepare("INSERT INTO iscritto (email, nome, cognome, password, data_nascita, ruolo, data_inizio_responsabile) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $nome, $cognome, $password, $dataNascita, $ruolo, $dataInizioResp]);
            
            jsonSuccess(['email' => $email, 'nome' => $nome, 'cognome' => $cognome]);
            break;
            
        case 'user':
            if ($method !== 'GET') jsonError('Metodo non consentito', 405);
            $user = getLoggedUser();
            if (!$user) jsonError('Non autenticato', 401);
            jsonSuccess($user);
            break;
            
        case 'logout':
            if ($method !== 'POST') jsonError('Metodo non consentito', 405);
            session_destroy();
            jsonSuccess(null);
            break;
            
        /* ==================== PRENOTAZIONI ==================== */
        
        case 'prenotazioni':
            if ($method === 'GET') {
                // Visualizza prenotazioni per sala e settimana
                $salaId = isset($_GET['sala_id']) ? (int)$_GET['sala_id'] : null;
                $week = $_GET['week'] ?? date('Y-m-d');
                
                $lunedi = getLunediSettimana($week);
                $domenica = (new DateTime($lunedi))->modify('+6 days')->format('Y-m-d');
                
                $pdo = getDB();
                
                $sql = "SELECT p.*, s.nome_sala, s.nome_settore, s.capienza,
                        i.nome AS resp_nome, i.cognome AS resp_cognome
                        FROM prenotazione p
                        JOIN sala s ON p.sala_id = s.id
                        JOIN iscritto i ON p.responsabile_email = i.email
                        WHERE DATE(p.data_ora_inizio) BETWEEN ? AND ?";
                $params = [$lunedi, $domenica];
                
                if ($salaId) {
                    $sql .= " AND p.sala_id = ?";
                    $params[] = $salaId;
                }
                
                $sql .= " ORDER BY p.data_ora_inizio";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $prenotazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Aggiungi conteggio partecipanti
                foreach ($prenotazioni as &$p) {
                    $p['num_partecipanti'] = contaPartecipanti($p['id']);
                }
                
                jsonSuccess(['prenotazioni' => $prenotazioni, 'settimana_inizio' => $lunedi]);
                
            } elseif ($method === 'POST') {
                // Crea nuova prenotazione (solo responsabili)
                $user = requireResponsabile();
                $data = json_decode(file_get_contents('php://input'), true);
                
                $salaId = (int)($data['sala_id'] ?? 0);
                $dataOraInizio = $data['data_ora_inizio'] ?? '';
                $durata = (int)($data['durata'] ?? 0);
                $attivita = sanitize($data['attivita'] ?? '');
                $criterio = $data['criterio'] ?? 'selezione';
                $invitati = $data['invitati'] ?? []; // array di email
                
                if (!$salaId || empty($dataOraInizio) || $durata <= 0 || empty($attivita)) {
                    jsonError('Tutti i campi sono obbligatori');
                }
                
                // Validazione orario
                if (!validaOrarioPrenotazione($dataOraInizio)) {
                    jsonError('Le prenotazioni devono essere ad ore intere tra le 09:00 e le 23:00');
                }
                
                // Verifica fine entro le 24:00
                $oraInizio = (int)(new DateTime($dataOraInizio))->format('H');
                if ($oraInizio + $durata > 24) {
                    jsonError('La prenotazione deve terminare entro le 24:00');
                }
                
                // Verifica sala esiste e ottieni settore
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT * FROM sala WHERE id = ?");
                $stmt->execute([$salaId]);
                $sala = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$sala) {
                    jsonError('Sala non trovata');
                }
                
                // Verifica sovrapposizione sala
                if (esisteSovrapposizioneSala($salaId, $dataOraInizio, $durata)) {
                    jsonError('La sala è già occupata in questo orario');
                }
                
                // Inserisci prenotazione
                $stmt = $pdo->prepare("INSERT INTO prenotazione (data_ora_inizio, durata, attivita, criterio, nome_settore, sala_id, responsabile_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$dataOraInizio, $durata, $attivita, $criterio, $sala['nome_settore'], $salaId, $user['email']]);
                $prenotazioneId = $pdo->lastInsertId();
                
                // Crea inviti se specificati
                if (!empty($invitati)) {
                    $stmtInvito = $pdo->prepare("INSERT INTO invito (iscritto_email, prenotazione_id) VALUES (?, ?)");
                    foreach ($invitati as $emailInvitato) {
                        $stmtInvito->execute([sanitize($emailInvitato), $prenotazioneId]);
                    }
                }
                
                jsonSuccess(['id' => $prenotazioneId]);
                
            } elseif ($method === 'DELETE') {
                // Cancella prenotazione
                $user = requireResponsabile();
                $id = (int)($_GET['id'] ?? 0);
                
                if (!$id) jsonError('ID prenotazione richiesto');
                
                $prenotazione = getPrenotazione($id);
                if (!$prenotazione) jsonError('Prenotazione non trovata');
                
                if ($prenotazione['responsabile_email'] !== $user['email']) {
                    jsonError('Puoi cancellare solo le tue prenotazioni', 403);
                }
                
                $pdo = getDB();
                $pdo->prepare("DELETE FROM invito WHERE prenotazione_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM prenotazione WHERE id = ?")->execute([$id]);
                
                jsonSuccess(null);
                
            } elseif ($method === 'PUT') {
                // Modifica prenotazione
                $user = requireResponsabile();
                $data = json_decode(file_get_contents('php://input'), true);
                $id = (int)($data['id'] ?? 0);
                
                if (!$id) jsonError('ID prenotazione richiesto');
                
                $prenotazione = getPrenotazione($id);
                if (!$prenotazione) jsonError('Prenotazione non trovata');
                
                if ($prenotazione['responsabile_email'] !== $user['email']) {
                    jsonError('Puoi modificare solo le tue prenotazioni', 403);
                }
                
                $dataOraInizio = $data['data_ora_inizio'] ?? $prenotazione['data_ora_inizio'];
                $durata = (int)($data['durata'] ?? $prenotazione['durata']);
                $attivita = sanitize($data['attivita'] ?? $prenotazione['attivita']);
                
                if (!validaOrarioPrenotazione($dataOraInizio)) {
                    jsonError('Le prenotazioni devono essere ad ore intere tra le 09:00 e le 23:00');
                }
                
                if (esisteSovrapposizioneSala($prenotazione['sala_id'], $dataOraInizio, $durata, $id)) {
                    jsonError('La sala è già occupata in questo orario');
                }
                
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE prenotazione SET data_ora_inizio = ?, durata = ?, attivita = ? WHERE id = ?");
                $stmt->execute([$dataOraInizio, $durata, $attivita, $id]);
                
                jsonSuccess(null);
                
            } else {
                jsonError('Metodo non consentito', 405);
            }
            break;
            
        /* ==================== INVITI ==================== */
        
        case 'inviti':
            $user = requireLogin();
            
            if ($method === 'GET') {
                // Lista inviti per l'utente loggato
                $pdo = getDB();
                $stmt = $pdo->prepare("
                    SELECT i.*, p.data_ora_inizio, p.durata, p.attivita, 
                           s.nome_sala, s.nome_settore, s.capienza,
                           r.nome AS resp_nome, r.cognome AS resp_cognome
                    FROM invito i
                    JOIN prenotazione p ON i.prenotazione_id = p.id
                    JOIN sala s ON p.sala_id = s.id
                    JOIN iscritto r ON p.responsabile_email = r.email
                    WHERE i.iscritto_email = ?
                    AND p.data_ora_inizio >= NOW()
                    ORDER BY p.data_ora_inizio
                ");
                $stmt->execute([$user['email']]);
                $inviti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($inviti as &$inv) {
                    $inv['num_partecipanti'] = contaPartecipanti($inv['prenotazione_id']);
                }
                
                jsonSuccess($inviti);
            }
            break;
            
        case 'inviti/rispondi':
            if ($method !== 'POST') jsonError('Metodo non consentito', 405);
            $user = requireLogin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $prenotazioneId = (int)($data['prenotazione_id'] ?? 0);
            $risposta = $data['risposta'] ?? '';
            $motivazione = sanitize($data['motivazione'] ?? '');
            
            if (!$prenotazioneId || !in_array($risposta, ['si', 'no'])) {
                jsonError('Dati non validi');
            }
            
            // Rifiuto richiede motivazione
            if ($risposta === 'no' && empty($motivazione)) {
                jsonError('La motivazione è obbligatoria per rifiutare un invito');
            }
            
            $prenotazione = getPrenotazione($prenotazioneId);
            if (!$prenotazione) jsonError('Prenotazione non trovata');
            
            // Verifica invito esiste
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM invito WHERE iscritto_email = ? AND prenotazione_id = ?");
            $stmt->execute([$user['email'], $prenotazioneId]);
            $invito = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invito) jsonError('Invito non trovato');
            
            if ($risposta === 'si') {
                // Verifica capienza
                $partecipanti = contaPartecipanti($prenotazioneId);
                $capienza = getCapienzaSala($prenotazione['sala_id']);
                
                if ($partecipanti >= $capienza) {
                    jsonError('Posti esauriti per questa prenotazione');
                }
                
                // Verifica sovrapposizione impegni utente
                if (esisteSovrapposizioneUtente($user['email'], $prenotazione['data_ora_inizio'], $prenotazione['durata'], $prenotazioneId)) {
                    jsonError('Hai già un impegno in questo orario');
                }
            }
            
            $stmt = $pdo->prepare("UPDATE invito SET risposta = ?, motivazione = ?, data_ora_risposta = NOW() WHERE iscritto_email = ? AND prenotazione_id = ?");
            $stmt->execute([$risposta, $motivazione ?: null, $user['email'], $prenotazioneId]);
            
            jsonSuccess(null);
            break;
            
        case 'inviti/rimuovi':
            if ($method !== 'POST') jsonError('Metodo non consentito', 405);
            $user = requireLogin();
            $data = json_decode(file_get_contents('php://input'), true);
            $prenotazioneId = (int)($data['prenotazione_id'] ?? 0);
            
            if (!$prenotazioneId) jsonError('ID prenotazione richiesto');
            
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE invito SET risposta = 'attesa', motivazione = NULL, data_ora_risposta = NULL WHERE iscritto_email = ? AND prenotazione_id = ?");
            $stmt->execute([$user['email'], $prenotazioneId]);
            
            jsonSuccess(null);
            break;
            
        /* ==================== IMPEGNI UTENTE ==================== */
        
        case 'impegni':
            $user = requireLogin();
            $week = $_GET['week'] ?? date('Y-m-d');
            
            $lunedi = getLunediSettimana($week);
            $domenica = (new DateTime($lunedi))->modify('+6 days')->format('Y-m-d');
            
            $pdo = getDB();
            $stmt = $pdo->prepare("
                SELECT p.*, s.nome_sala, s.nome_settore,
                       r.nome AS resp_nome, r.cognome AS resp_cognome
                FROM invito i
                JOIN prenotazione p ON i.prenotazione_id = p.id
                JOIN sala s ON p.sala_id = s.id
                JOIN iscritto r ON p.responsabile_email = r.email
                WHERE i.iscritto_email = ?
                AND i.risposta = 'si'
                AND DATE(p.data_ora_inizio) BETWEEN ? AND ?
                ORDER BY p.data_ora_inizio
            ");
            $stmt->execute([$user['email'], $lunedi, $domenica]);
            
            jsonSuccess(['impegni' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'settimana_inizio' => $lunedi]);
            break;
            
        /* ==================== DATI AUSILIARI ==================== */
        
        case 'sale':
            $pdo = getDB();
            $stmt = $pdo->query("SELECT s.*, se.responsabile_email FROM sala s JOIN settore se ON s.nome_settore = se.nome_settore ORDER BY s.nome_settore, s.nome_sala");
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'settori':
            $pdo = getDB();
            $stmt = $pdo->query("SELECT * FROM settore ORDER BY nome_settore");
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'iscritti':
            $pdo = getDB();
            $settore = $_GET['settore'] ?? null;
            $ruolo = $_GET['ruolo'] ?? null;
            
            $sql = "SELECT email, nome, cognome, ruolo FROM iscritto WHERE 1=1";
            $params = [];
            
            if ($settore) {
                // Per filtrare per settore, usiamo il settore del responsabile della prenotazione
                // Qui assumiamo che gli iscritti possano appartenere a più settori tramite inviti
            }
            if ($ruolo) {
                $sql .= " AND ruolo = ?";
                $params[] = $ruolo;
            }
            
            $sql .= " ORDER BY cognome, nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'profilo':
            if ($method === 'PUT') {
                $user = requireLogin();
                $data = json_decode(file_get_contents('php://input'), true);
                
                $nome = sanitize($data['nome'] ?? $user['nome']);
                $cognome = sanitize($data['cognome'] ?? $user['cognome']);
                
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE iscritto SET nome = ?, cognome = ? WHERE email = ?");
                $stmt->execute([$nome, $cognome, $user['email']]);
                
                $_SESSION['user']['nome'] = $nome;
                $_SESSION['user']['cognome'] = $cognome;
                
                jsonSuccess($_SESSION['user']);
            }
            break;
            
        default:
            jsonError('Azione non trovata', 404);
    }
    
} catch (PDOException $e) {
    jsonError('Errore database: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    jsonError('Errore: ' . $e->getMessage(), 500);
}
