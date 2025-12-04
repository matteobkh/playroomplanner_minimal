<?php
/**
 * index.php - Pagina principale Play Room Planner
 */
require_once __DIR__ . '/common/config.php';
$user = $_SESSION['user'] ?? null;
$isResp = $user && $user['ruolo'] === 'responsabile' && $user['data_inizio_responsabile'];
?>
<!DOCTYPE html>
<html lang="it">
<head><?php require 'common/header.html'; ?></head>
<body data-user-email="<?= htmlspecialchars($user['email'] ?? '') ?>">
<?php require 'common/nav.php'; ?>

<!-- Container alert -->
<div id="alertContainer"></div>

<main class="container">
    <!-- Hero -->
    <div class="bg-primary text-white rounded p-4 mb-4">
        <h1><i class="bi bi-music-note-beamed"></i> Play Room Planner</h1>
        <p class="mb-0">Gestione prenotazioni sale prove</p>
    </div>

    <!-- PRENOTAZIONI SALE -->
    <section id="sale" class="mb-5">
        <h3><i class="bi bi-building"></i> Prenotazioni Sale</h3>
        <div class="row mb-3">
            <div class="col-md-4">
                <select id="salaFilter" class="form-select"></select>
            </div>
            <div class="col-md-8">
                <div class="week-nav justify-content-end">
                    <button class="btn btn-outline-primary" onclick="changeWeek(-1)"><i class="bi bi-chevron-left"></i> Prec</button>
                    <span id="weekDisplay" class="text-muted"></span>
                    <button class="btn btn-outline-primary" onclick="changeWeek(1)">Succ <i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <div id="prenotazioniContainer"><div class="text-center p-4"><div class="spinner-border text-primary"></div></div></div>
    </section>

    <?php if ($user): ?>
    <!-- INVITI -->
    <section id="inviti" class="mb-5">
        <h3><i class="bi bi-envelope"></i> I Miei Inviti</h3>
        <div id="invitiContainer"><div class="text-center p-4"><div class="spinner-border text-primary"></div></div></div>
    </section>

    <!-- IMPEGNI -->
    <section id="impegni" class="mb-5">
        <h3><i class="bi bi-calendar-check"></i> I Miei Impegni</h3>
        <div class="week-nav mb-3">
            <button class="btn btn-outline-secondary btn-sm" onclick="changeImpegniWeek(-1)"><i class="bi bi-chevron-left"></i></button>
            <span id="impegniWeekDisplay" class="text-muted"></span>
            <button class="btn btn-outline-secondary btn-sm" onclick="changeImpegniWeek(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div id="impegniContainer"><div class="text-center p-4"><div class="spinner-border text-primary"></div></div></div>
    </section>

    <?php if ($isResp): ?>
    <!-- NUOVA PRENOTAZIONE (solo responsabili) -->
    <section id="nuova-prenotazione" class="mb-5">
        <h3><i class="bi bi-plus-circle"></i> Nuova Prenotazione</h3>
        <div class="form-section">
            <form onsubmit="creaPrenotazione(event)">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sala</label>
                        <select name="sala_id" id="salaSelect" class="form-select" required></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data e Ora</label>
                        <input type="datetime-local" name="data_ora_inizio" class="form-control" step="3600" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Durata (ore)</label>
                        <input type="number" name="durata" class="form-control" min="1" max="15" value="1" required>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Attività</label>
                        <input type="text" name="attivita" class="form-control" placeholder="es. Prove musicali" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Invita partecipanti</label>
                    <div id="iscrittiCheckboxes" class="border rounded p-2" style="max-height:200px;overflow-y:auto;"></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crea</button>
            </form>
        </div>
    </section>
    <?php endif; endif; ?>
</main>

<!-- MODALS -->

<!-- Login -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-box-arrow-in-right"></i> Login</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <form onsubmit="login(event)">
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" class="btn btn-primary w-100">Accedi</button>
            </form>
        </div>
    </div></div>
</div>

<!-- Registrazione -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i> Registrazione</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <form onsubmit="register(event)">
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label">Nome</label><input type="text" name="nome" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Cognome</label><input type="text" name="cognome" class="form-control" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label">Data Nascita</label><input type="date" name="data_nascita" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Ruolo</label>
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
    </div></div>
</div>

<!-- Profilo -->
<div class="modal fade" id="profiloModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-circle"></i> Profilo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <?php if ($user): ?>
            <form onsubmit="salvaProfilo(event)">
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label">Nome</label><input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($user['nome']) ?>" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Cognome</label><input type="text" name="cognome" class="form-control" value="<?= htmlspecialchars($user['cognome']) ?>" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
                <div class="mb-3"><label class="form-label">Data Nascita</label><input type="date" name="data_nascita" class="form-control" value="<?= htmlspecialchars($user['data_nascita']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Ruolo</label><input type="text" class="form-control" value="<?= htmlspecialchars($user['ruolo']) ?>" disabled></div>
                <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-check-lg"></i> Salva</button>
            </form>
            <hr>
            <button type="button" class="btn btn-outline-danger w-100" onclick="cancellaProfilo()"><i class="bi bi-trash"></i> Cancella Account</button>
            <?php endif; ?>
        </div>
    </div></div>
</div>

<!-- Rifiuto invito -->
<div class="modal fade" id="rifiutaModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-x-circle"></i> Rifiuta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="rifiutaPrenotazioneId">
            <div class="mb-3"><label class="form-label">Motivazione</label><textarea id="motivazioneRifiuto" class="form-control" rows="3" required></textarea></div>
            <button type="button" class="btn btn-danger w-100" onclick="confermaRifiuto()">Conferma Rifiuto</button>
        </div>
    </div></div>
</div>

<!-- Modifica prenotazione -->
<div class="modal fade" id="modificaPrenotazioneModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil"></i> Modifica</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="modificaId">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Data e Ora</label><input type="datetime-local" id="modificaDataOra" class="form-control" step="3600" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">Durata</label><input type="number" id="modificaDurata" class="form-control" min="1" max="15" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">Attività</label><input type="text" id="modificaAttivita" class="form-control" required></div>
            </div>
            <div class="mb-3"><label class="form-label">Invitati</label><div id="modificaIscrittiCheckboxes" class="border rounded p-2" style="max-height:200px;overflow-y:auto;"></div></div>
            <button type="button" class="btn btn-primary w-100" onclick="salvaModificaPrenotazione()"><i class="bi bi-check-lg"></i> Salva</button>
        </div>
    </div></div>
</div>

<?php require 'common/footer.php'; ?>
</body>
</html>
