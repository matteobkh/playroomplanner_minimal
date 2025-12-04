<?php
/**
 * index.php - Pagina principale Play Room Planner
 * Entry point dell'applicazione
 */
require_once __DIR__ . '/common/config.php';
$user = $_SESSION['user'] ?? null;
$isResponsabile = $user && $user['ruolo'] === 'responsabile' && !empty($user['data_inizio_responsabile']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php require 'common/header.html'; ?>
</head>
<body>
    <?php require 'common/nav.php'; ?>
    
    <!-- Container messaggi -->
    <div id="alertContainer"></div>
    
    <main class="container">
        <!-- Hero -->
        <div class="bg-primary text-white rounded p-4 mb-4">
            <h1><i class="bi bi-music-note-beamed"></i> Play Room Planner</h1>
            <p class="mb-0">Gestione prenotazioni sale prove dell'associazione culturale</p>
        </div>
        
        <!-- SEZIONE SALE PROVE -->
        <section id="sale" class="mb-5">
            <h3><i class="bi bi-building"></i> Prenotazioni Sale Prove</h3>
            
            <!-- Filtro e navigazione settimana -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <select id="salaFilter" class="form-select">
                        <option value="">Tutte le sale</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="week-nav justify-content-end">
                        <button class="btn btn-outline-primary" onclick="changeWeek(-1)">
                            <i class="bi bi-chevron-left"></i> Settimana precedente
                        </button>
                        <span id="weekDisplay" class="text-muted"></span>
                        <button class="btn btn-outline-primary" onclick="changeWeek(1)">
                            Settimana successiva <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="prenotazioniContainer">
                <div class="loading"><div class="spinner-border text-primary"></div></div>
            </div>
        </section>
        
        <?php if ($user): ?>
        <!-- SEZIONE INVITI -->
        <section id="inviti" class="mb-5">
            <h3><i class="bi bi-envelope"></i> I Miei Inviti</h3>
            <div id="invitiContainer">
                <div class="loading"><div class="spinner-border text-primary"></div></div>
            </div>
        </section>
        
        <!-- SEZIONE IMPEGNI -->
        <section id="impegni" class="mb-5">
            <h3><i class="bi bi-calendar-check"></i> I Miei Impegni</h3>
            
            <div class="week-nav mb-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="changeImpegniWeek(-1)">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span id="impegniWeekDisplay" class="text-muted"></span>
                <button class="btn btn-outline-secondary btn-sm" onclick="changeImpegniWeek(1)">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            
            <div id="impegniContainer">
                <div class="loading"><div class="spinner-border text-primary"></div></div>
            </div>
        </section>
        
        <?php if ($isResponsabile): ?>
        <!-- SEZIONE NUOVA PRENOTAZIONE (solo responsabili) -->
        <section id="nuova-prenotazione" class="mb-5">
            <h3><i class="bi bi-plus-circle"></i> Nuova Prenotazione</h3>
            
            <div class="form-section">
                <form onsubmit="creaPrenotazione(event)">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sala</label>
                            <select name="sala_id" id="salaSelect" class="form-select" required>
                                <option value="">Seleziona sala...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data e Ora Inizio</label>
                            <input type="datetime-local" name="data_ora_inizio" class="form-control" step="3600" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Durata (ore)</label>
                            <input type="number" name="durata" class="form-control" min="1" max="15" value="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Attività</label>
                            <input type="text" name="attivita" class="form-control" placeholder="es. Prove musicali" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Criterio Inviti</label>
                            <select name="criterio" class="form-select">
                                <option value="selezione">Selezione manuale</option>
                                <option value="tutti">Tutti</option>
                                <option value="settore">Per settore</option>
                                <option value="ruolo">Per ruolo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Invita partecipanti</label>
                        <div id="iscrittiCheckboxes" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            <div class="loading"><div class="spinner-border spinner-border-sm"></div></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Crea Prenotazione
                    </button>
                </form>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <!-- MODAL LOGIN -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-in-right"></i> Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form onsubmit="login(event)">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Accedi</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL REGISTRAZIONE -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Registrazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form onsubmit="register(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="nome" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cognome</label>
                                <input type="text" name="cognome" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data di Nascita</label>
                                <input type="date" name="data_nascita" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ruolo</label>
                                <select name="ruolo" class="form-select">
                                    <option value="allievo">Allievo</option>
                                    <option value="docente">Docente</option>
                                    <option value="tecnico">Tecnico</option>
                                    <option value="responsabile">Responsabile</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Registrati</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL PROFILO -->
    <div class="modal fade" id="profiloModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-circle"></i> Il Mio Profilo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($user): ?>
                    <table class="table">
                        <tr><th>Nome</th><td><?= htmlspecialchars($user['nome']) ?></td></tr>
                        <tr><th>Cognome</th><td><?= htmlspecialchars($user['cognome']) ?></td></tr>
                        <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
                        <tr><th>Ruolo</th><td><span class="badge bg-secondary"><?= htmlspecialchars($user['ruolo']) ?></span></td></tr>
                        <tr><th>Data Nascita</th><td><?= htmlspecialchars($user['data_nascita']) ?></td></tr>
                        <?php if ($isResponsabile): ?>
                        <tr><th>Responsabile dal</th><td><?= htmlspecialchars($user['data_inizio_responsabile']) ?></td></tr>
                        <?php endif; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MODAL RIFIUTO INVITO -->
    <div class="modal fade" id="rifiutaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> Rifiuta Invito</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rifiutaPrenotazioneId">
                    <div class="mb-3">
                        <label class="form-label">Motivazione (obbligatoria)</label>
                        <textarea id="motivazioneRifiuto" class="form-control" rows="3" required placeholder="Indica il motivo del rifiuto..."></textarea>
                    </div>
                    <button type="button" class="btn btn-danger w-100" onclick="confermaRifiuto()">
                        Conferma Rifiuto
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php require 'common/footer.php'; ?>
</body>
</html>
