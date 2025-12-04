<?php
/**
 * api.php - API REST centralizzata
 * Gestisce: login, registrazione, prenotazioni, inviti, profilo
 */

require_once __DIR__ . '/common/util.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$pdo = getDB();

try {
    switch ($action) {

        /* === AUTENTICAZIONE === */

        case 'login':
            // Verifica credenziali e crea sessione
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT * FROM iscritto WHERE email = ?");
            $stmt->execute([sanitize($data['email'] ?? '')]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || ($data['password'] ?? '') !== $user['password']) {
                jsonError('Credenziali non valide', 401);
            }
            unset($user['password']);
            $_SESSION['user'] = $user;
            jsonSuccess($user);

        case 'register':
            // Registra nuovo utente
            $data = json_decode(file_get_contents('php://input'), true);
            $email = sanitize($data['email'] ?? '');
            $nome = sanitize($data['nome'] ?? '');
            $cognome = sanitize($data['cognome'] ?? '');
            $password = $data['password'] ?? '';
            $nascita = $data['data_nascita'] ?? '';
            $ruolo = $data['ruolo'] ?? 'allievo';
            
            if (!$email || !$nome || !$cognome || !$password || !$nascita) {
                jsonError('Tutti i campi sono obbligatori');
            }
            
            // Verifica email unica
            $stmt = $pdo->prepare("SELECT 1 FROM iscritto WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) jsonError('Email già registrata');
            
            // Data inizio responsabile solo se ruolo = responsabile
            $dataResp = ($ruolo === 'responsabile') ? date('Y-m-d') : null;
            
            $stmt = $pdo->prepare("INSERT INTO iscritto (email, nome, cognome, password, data_nascita, ruolo, data_inizio_responsabile) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $nome, $cognome, $password, $nascita, $ruolo, $dataResp]);
            jsonSuccess(['email' => $email]);

        case 'logout':
            // Distrugge sessione
            session_destroy();
            jsonSuccess(null);

        case 'user':
            // Restituisce utente loggato
            jsonSuccess(getLoggedUser());

        /* === PRENOTAZIONI === */

        case 'prenotazioni':
            if ($method === 'GET') {
                // Lista prenotazioni settimanali, filtrabile per sala
                $salaId = $_GET['sala_id'] ?? null;
                $lunedi = getLunedi($_GET['week'] ?? date('Y-m-d'));
                $domenica = (new DateTime($lunedi))->modify('+6 days')->format('Y-m-d');
                
                $sql = "SELECT p.*, s.nome_sala, s.nome_settore, s.capienza, i.nome AS resp_nome, i.cognome AS resp_cognome
                        FROM prenotazione p
                        JOIN sala s ON p.sala_id = s.id
                        JOIN iscritto i ON p.responsabile_email = i.email
                        WHERE DATE(p.data_ora_inizio) BETWEEN ? AND ?";
                $params = [$lunedi, $domenica];
                if ($salaId) { $sql .= " AND p.sala_id = ?"; $params[] = $salaId; }
                $sql .= " ORDER BY p.data_ora_inizio";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $prenotazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Aggiungi conteggio partecipanti
                foreach ($prenotazioni as &$p) $p['num_partecipanti'] = contaPartecipanti($p['id']);
                
                jsonSuccess(['prenotazioni' => $prenotazioni, 'settimana_inizio' => $lunedi]);
            }
            
            if ($method === 'POST') {
                // Crea nuova prenotazione (solo responsabili)
                $user = requireResponsabile();
                $data = json_decode(file_get_contents('php://input'), true);
                
                $salaId = (int)($data['sala_id'] ?? 0);
                $inizio = $data['data_ora_inizio'] ?? '';
                $durata = (int)($data['durata'] ?? 0);
                $attivita = sanitize($data['attivita'] ?? '');
                $invitati = $data['invitati'] ?? [];
                
                if (!$salaId || !$inizio || $durata <= 0 || !$attivita) jsonError('Campi obbligatori mancanti');
                if (!validaOrario($inizio)) jsonError('Orario non valido (ore intere, 9-23)');
                if (sovrapposizioneSala($salaId, $inizio, $durata)) jsonError('Sala già occupata');
                
                // Ottieni settore dalla sala
                $stmt = $pdo->prepare("SELECT nome_settore FROM sala WHERE id = ?");
                $stmt->execute([$salaId]);
                $settore = $stmt->fetchColumn();
                if (!$settore) jsonError('Sala non trovata');
                
                // Inserisci prenotazione
                $stmt = $pdo->prepare("INSERT INTO prenotazione (data_ora_inizio, durata, attivita, nome_settore, sala_id, responsabile_email) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$inizio, $durata, $attivita, $settore, $salaId, $user['email']]);
                $id = $pdo->lastInsertId();
                
                // Crea inviti
                if ($invitati) {
                    $stmt = $pdo->prepare("INSERT INTO invito (iscritto_email, prenotazione_id) VALUES (?, ?)");
                    foreach ($invitati as $email) $stmt->execute([sanitize($email), $id]);
                }
                jsonSuccess(['id' => $id]);
            }
            
            if ($method === 'PUT') {
                // Modifica prenotazione (solo proprietario)
                $user = requireResponsabile();
                $data = json_decode(file_get_contents('php://input'), true);
                $id = (int)($data['id'] ?? 0);
                
                $p = getPrenotazione($id);
                if (!$p) jsonError('Prenotazione non trovata');
                if ($p['responsabile_email'] !== $user['email']) jsonError('Non autorizzato', 403);
                
                $inizio = $data['data_ora_inizio'] ?? $p['data_ora_inizio'];
                $durata = (int)($data['durata'] ?? $p['durata']);
                $attivita = sanitize($data['attivita'] ?? $p['attivita']);
                $invitati = $data['invitati'] ?? null;
                
                if (!validaOrario($inizio)) jsonError('Orario non valido');
                if (sovrapposizioneSala($p['sala_id'], $inizio, $durata, $id)) jsonError('Sala già occupata');
                
                // Aggiorna prenotazione
                $stmt = $pdo->prepare("UPDATE prenotazione SET data_ora_inizio = ?, durata = ?, attivita = ? WHERE id = ?");
                $stmt->execute([$inizio, $durata, $attivita, $id]);
                
                // Aggiorna invitati se specificati
                if ($invitati !== null) {
                    // Rimuovi inviti in attesa non più selezionati
                    $placeholders = $invitati ? implode(',', array_fill(0, count($invitati), '?')) : "''";
                    $stmt = $pdo->prepare("DELETE FROM invito WHERE prenotazione_id = ? AND risposta = 'attesa' AND iscritto_email NOT IN ($placeholders)");
                    $stmt->execute(array_merge([$id], $invitati));
                    
                    // Aggiungi nuovi inviti
                    $stmtCheck = $pdo->prepare("SELECT 1 FROM invito WHERE iscritto_email = ? AND prenotazione_id = ?");
                    $stmtIns = $pdo->prepare("INSERT INTO invito (iscritto_email, prenotazione_id) VALUES (?, ?)");
                    foreach ($invitati as $email) {
                        $stmtCheck->execute([sanitize($email), $id]);
                        if (!$stmtCheck->fetch()) $stmtIns->execute([sanitize($email), $id]);
                    }
                }
                jsonSuccess(null);
            }
            
            if ($method === 'DELETE') {
                // Cancella prenotazione (solo proprietario)
                $user = requireResponsabile();
                $id = (int)($_GET['id'] ?? 0);
                
                $p = getPrenotazione($id);
                if (!$p) jsonError('Prenotazione non trovata');
                if ($p['responsabile_email'] !== $user['email']) jsonError('Non autorizzato', 403);
                
                $pdo->prepare("DELETE FROM invito WHERE prenotazione_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM prenotazione WHERE id = ?")->execute([$id]);
                jsonSuccess(null);
            }
            break;

        /* === INVITI === */

        case 'invitati':
            // Lista invitati di una prenotazione
            $id = (int)($_GET['prenotazione_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM invito WHERE prenotazione_id = ?");
            $stmt->execute([$id]);
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));

        case 'inviti':
            // Lista inviti dell'utente loggato (futuri)
            $user = requireLogin();
            $stmt = $pdo->prepare("
                SELECT i.*, p.data_ora_inizio, p.durata, p.attivita, s.nome_sala, s.nome_settore, s.capienza, r.nome AS resp_nome, r.cognome AS resp_cognome
                FROM invito i
                JOIN prenotazione p ON i.prenotazione_id = p.id
                JOIN sala s ON p.sala_id = s.id
                JOIN iscritto r ON p.responsabile_email = r.email
                WHERE i.iscritto_email = ? AND p.data_ora_inizio >= NOW()
                ORDER BY p.data_ora_inizio");
            $stmt->execute([$user['email']]);
            $inviti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($inviti as &$inv) $inv['num_partecipanti'] = contaPartecipanti($inv['prenotazione_id']);
            jsonSuccess($inviti);

        case 'inviti/rispondi':
            // Accetta o rifiuta invito
            $user = requireLogin();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['prenotazione_id'] ?? 0);
            $risposta = $data['risposta'] ?? '';
            $motivazione = sanitize($data['motivazione'] ?? '');
            
            if (!in_array($risposta, ['si', 'no'])) jsonError('Risposta non valida');
            if ($risposta === 'no' && !$motivazione) jsonError('Motivazione obbligatoria per rifiuto');
            
            $p = getPrenotazione($id);
            if (!$p) jsonError('Prenotazione non trovata');
            
            if ($risposta === 'si') {
                if (contaPartecipanti($id) >= getCapienza($p['sala_id'])) jsonError('Posti esauriti');
                if (sovrapposizioneUtente($user['email'], $p['data_ora_inizio'], $p['durata'], $id)) jsonError('Hai già un impegno');
            }
            
            $stmt = $pdo->prepare("UPDATE invito SET risposta = ?, motivazione = ?, data_ora_risposta = NOW() WHERE iscritto_email = ? AND prenotazione_id = ?");
            $stmt->execute([$risposta, $motivazione ?: null, $user['email'], $id]);
            jsonSuccess(null);

        case 'inviti/rimuovi':
            // Rimuovi partecipazione (reimposta a 'attesa')
            $user = requireLogin();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['prenotazione_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE invito SET risposta = 'attesa', motivazione = NULL, data_ora_risposta = NULL WHERE iscritto_email = ? AND prenotazione_id = ?");
            $stmt->execute([$user['email'], $id]);
            jsonSuccess(null);

        /* === IMPEGNI === */

        case 'impegni':
            // Lista impegni confermati dell'utente per settimana
            $user = requireLogin();
            $lunedi = getLunedi($_GET['week'] ?? date('Y-m-d'));
            $domenica = (new DateTime($lunedi))->modify('+6 days')->format('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT p.*, s.nome_sala, s.nome_settore, r.nome AS resp_nome, r.cognome AS resp_cognome
                FROM invito i
                JOIN prenotazione p ON i.prenotazione_id = p.id
                JOIN sala s ON p.sala_id = s.id
                JOIN iscritto r ON p.responsabile_email = r.email
                WHERE i.iscritto_email = ? AND i.risposta = 'si' AND DATE(p.data_ora_inizio) BETWEEN ? AND ?
                ORDER BY p.data_ora_inizio");
            $stmt->execute([$user['email'], $lunedi, $domenica]);
            jsonSuccess(['impegni' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'settimana_inizio' => $lunedi]);

        /* === DATI AUSILIARI === */

        case 'sale':
            // Lista tutte le sale
            $stmt = $pdo->query("SELECT * FROM sala ORDER BY nome_settore, nome_sala");
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));

        case 'iscritti':
            // Lista tutti gli iscritti
            $stmt = $pdo->query("SELECT email, nome, cognome, ruolo FROM iscritto ORDER BY cognome, nome");
            jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));

        /* === PROFILO === */

        case 'profilo':
            $user = requireLogin();
            
            if ($method === 'PUT') {
                // Modifica dati profilo
                $data = json_decode(file_get_contents('php://input'), true);
                $nome = sanitize($data['nome'] ?? $user['nome']);
                $cognome = sanitize($data['cognome'] ?? $user['cognome']);
                $nascita = $data['data_nascita'] ?? $user['data_nascita'];
                
                $stmt = $pdo->prepare("UPDATE iscritto SET nome = ?, cognome = ?, data_nascita = ? WHERE email = ?");
                $stmt->execute([$nome, $cognome, $nascita, $user['email']]);
                
                $_SESSION['user'] = array_merge($user, compact('nome', 'cognome') + ['data_nascita' => $nascita]);
                jsonSuccess($_SESSION['user']);
            }
            
            if ($method === 'DELETE') {
                // Cancella account e tutti i dati associati
                $pdo->prepare("DELETE FROM invito WHERE iscritto_email = ?")->execute([$user['email']]);
                $pdo->prepare("DELETE FROM invito WHERE prenotazione_id IN (SELECT id FROM prenotazione WHERE responsabile_email = ?)")->execute([$user['email']]);
                $pdo->prepare("DELETE FROM prenotazione WHERE responsabile_email = ?")->execute([$user['email']]);
                $pdo->prepare("DELETE FROM iscritto WHERE email = ?")->execute([$user['email']]);
                session_destroy();
                jsonSuccess(null);
            }
            break;

        default:
            jsonError('Azione non trovata', 404);
    }
} catch (Exception $e) {
    jsonError('Errore: ' . $e->getMessage(), 500);
}
