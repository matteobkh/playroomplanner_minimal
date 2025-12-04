# Play Room Planner

Applicazione Web per la gestione delle prenotazioni delle sale prove di un'associazione culturale.

## Requisiti

- XAMPP per Mac (o ambiente equivalente con PHP 8.x e MySQL)
- Browser web moderno

## Installazione

### 1. Copia i file
Copia l'intera cartella `playroomplanner` nella directory `htdocs` di XAMPP:
```
/Applications/XAMPP/htdocs/playroomplanner/
```

### 2. Crea il database
1. Avvia XAMPP (Apache e MySQL)
2. Apri phpMyAdmin: http://localhost/phpmyadmin
3. Importa il file `database.sql` oppure esegui lo script SQL manualmente

### 3. Configurazione (opzionale)
Se le credenziali del database sono diverse, modifica il file `common/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'playroomplanner');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Avvia l'applicazione
Apri nel browser: http://localhost/playroomplanner/

## Credenziali di Test

| Email | Password | Ruolo |
|-------|----------|-------|
| resp.musica@example.com | password | Responsabile |
| resp.teatro@example.com | password | Responsabile |
| resp.ballo@example.com | password | Responsabile |
| allievo1@example.com | password | Allievo |
| docente1@example.com | password | Docente |
| tecnico1@example.com | password | Tecnico |

## Struttura del Progetto

```
playroomplanner/
├── index.php           # Pagina principale (frontend)
├── api.php             # API centralizzata (backend)
├── database.sql        # Script creazione/popolamento DB
├── common/
│   ├── config.php      # Configurazione DB e sessione
│   ├── util.php        # Funzioni utility condivise
│   ├── header.html     # Head HTML comune
│   ├── nav.php         # Navbar dinamica
│   └── footer.php      # Footer e script JS
├── css/
│   └── style.css       # Stili personalizzati
├── js/
│   └── app.js          # Logica JavaScript frontend
└── img/                # Immagini (opzionale)
```

## Funzionalità Implementate

### Autenticazione
- Login con email e password
- Registrazione nuovi utenti
- Logout con distruzione sessione
- Visualizzazione profilo utente

### Gestione Inviti (tutti gli utenti loggati)
- Visualizzazione inviti pendenti
- Accettazione invito (con verifica capienza e sovrapposizioni)
- Rifiuto invito (con motivazione obbligatoria)
- Rimozione partecipazione già confermata

### Gestione Prenotazioni (solo responsabili)
- Creazione nuova prenotazione
- Selezione sala, data/ora, durata, attività
- Invito partecipanti al momento della creazione
- Cancellazione prenotazione propria
- Modifica prenotazione propria

### Visualizzazione
- Calendario settimanale prenotazioni per sala
- Lista impegni personali settimanali
- Navigazione tra settimane

## API Endpoints

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | api.php?action=login | Login utente |
| POST | api.php?action=register | Registrazione |
| GET | api.php?action=user | Profilo utente loggato |
| POST | api.php?action=logout | Logout |
| GET | api.php?action=prenotazioni | Lista prenotazioni |
| POST | api.php?action=prenotazioni | Crea prenotazione |
| PUT | api.php?action=prenotazioni | Modifica prenotazione |
| DELETE | api.php?action=prenotazioni | Cancella prenotazione |
| GET | api.php?action=inviti | Lista inviti utente |
| POST | api.php?action=inviti/rispondi | Accetta/rifiuta invito |
| POST | api.php?action=inviti/rimuovi | Rimuovi partecipazione |
| GET | api.php?action=impegni | Impegni settimanali |
| GET | api.php?action=sale | Lista sale |
| GET | api.php?action=settori | Lista settori |
| GET | api.php?action=iscritti | Lista iscritti |

## Validazioni Implementate

- Prenotazioni solo ad ore intere (minuti = 0)
- Orario inizio tra 09:00 e 23:00
- Nessuna sovrapposizione prenotazioni nella stessa sala
- Nessuna sovrapposizione impegni per utente
- Capienza sala non superabile
- Rifiuto invito richiede motivazione
- Ruolo "responsabile" richiede data_inizio_responsabile

## Tecnologie Utilizzate

- **Backend**: PHP 8.x con PDO per MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Vanilla JS con Fetch API
- **Database**: MySQL con charset utf8mb4
- **Sicurezza**: password_hash/password_verify, sessioni PHP, sanitizzazione input

## Note per lo Sviluppo

- Le risposte API seguono il formato: `{ "ok": true/false, "data": ..., "error": "..." }`
- La sessione viene avviata in `config.php` incluso in ogni richiesta
- Le funzioni di validazione sono centralizzate in `util.php`
- Il frontend usa Bootstrap CDN, nessuna dipendenza locale
