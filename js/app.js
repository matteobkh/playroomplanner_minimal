/**
 * app.js - Logica frontend Play Room Planner
 * Gestisce tutte le interazioni con le API
 */

/* ==================== UTILITY ==================== */

/**
 * Funzione generica per chiamate fetch
 */
async function fetchJSON(action, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    const url = method === 'GET' && data 
        ? `api.php?action=${action}&${new URLSearchParams(data)}`
        : `api.php?action=${action}`;
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!result.ok) {
            throw new Error(result.error || 'Errore sconosciuto');
        }
        
        return result.data;
    } catch (error) {
        throw error;
    }
}

/**
 * Mostra messaggio alert
 */
function showAlert(message, type = 'success') {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.appendChild(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

/**
 * Formatta data/ora
 */
function formatDateTime(dateStr) {
    const dt = new Date(dateStr);
    return dt.toLocaleString('it-IT', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formatta solo la data
 */
function formatDate(dateStr) {
    const dt = new Date(dateStr);
    return dt.toLocaleDateString('it-IT', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

/**
 * Ottiene lunedì della settimana
 */
function getMonday(d) {
    const date = new Date(d);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(date.setDate(diff));
}

/* ==================== AUTENTICAZIONE ==================== */

/**
 * Login
 */
async function login(event) {
    event.preventDefault();
    const form = event.target;
    
    try {
        await fetchJSON('login', 'POST', {
            email: form.email.value,
            password: form.password.value
        });
        
        showAlert('Login effettuato con successo');
        setTimeout(() => location.reload(), 1000);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/**
 * Registrazione
 */
async function register(event) {
    event.preventDefault();
    const form = event.target;
    
    try {
        await fetchJSON('register', 'POST', {
            email: form.email.value,
            password: form.password.value,
            nome: form.nome.value,
            cognome: form.cognome.value,
            data_nascita: form.data_nascita.value,
            ruolo: form.ruolo.value
        });
        
        showAlert('Registrazione completata! Effettua il login.');
        bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/**
 * Logout
 */
async function logout() {
    try {
        await fetchJSON('logout', 'POST');
        location.reload();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/**
 * Mostra profilo
 */
function showProfilo() {
    const modal = new bootstrap.Modal(document.getElementById('profiloModal'));
    modal.show();
}

/* ==================== PRENOTAZIONI ==================== */

let currentWeek = new Date();

/**
 * Carica prenotazioni settimanali per una sala
 */
async function loadPrenotazioni(salaId = null) {
    const container = document.getElementById('prenotazioniContainer');
    if (!container) return;
    
    const weekStr = currentWeek.toISOString().split('T')[0];
    
    try {
        const params = { week: weekStr };
        if (salaId) params.sala_id = salaId;
        
        const result = await fetchJSON('prenotazioni', 'GET', params);
        
        // Aggiorna display settimana
        const weekDisplay = document.getElementById('weekDisplay');
        if (weekDisplay) {
            const monday = new Date(result.settimana_inizio);
            const sunday = new Date(monday);
            sunday.setDate(sunday.getDate() + 6);
            weekDisplay.textContent = `${formatDate(monday)} - ${formatDate(sunday)}`;
        }
        
        renderPrenotazioni(result.prenotazioni, container);
    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

/**
 * Renderizza lista prenotazioni
 */
function renderPrenotazioni(prenotazioni, container) {
    if (!prenotazioni.length) {
        container.innerHTML = '<div class="alert alert-info">Nessuna prenotazione per questa settimana</div>';
        return;
    }
    
    let html = '<div class="row">';
    prenotazioni.forEach(p => {
        const fine = new Date(new Date(p.data_ora_inizio).getTime() + p.durata * 3600000);
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card prenotazione-card h-100">
                    <div class="card-header bg-primary text-white">
                        <strong>${p.nome_sala}</strong> - ${p.nome_settore}
                    </div>
                    <div class="card-body">
                        <h6 class="card-title">${p.attivita}</h6>
                        <p class="mb-1"><i class="bi bi-calendar"></i> ${formatDateTime(p.data_ora_inizio)}</p>
                        <p class="mb-1"><i class="bi bi-clock"></i> Durata: ${p.durata}h</p>
                        <p class="mb-1"><i class="bi bi-people"></i> ${p.num_partecipanti}/${p.capienza} partecipanti</p>
                        <p class="mb-0"><i class="bi bi-person"></i> ${p.resp_nome} ${p.resp_cognome}</p>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Navigazione settimana
 */
function changeWeek(delta) {
    currentWeek.setDate(currentWeek.getDate() + (delta * 7));
    loadPrenotazioni(document.getElementById('salaFilter')?.value || null);
}

/* ==================== INVITI ==================== */

/**
 * Carica inviti utente
 */
async function loadInviti() {
    const container = document.getElementById('invitiContainer');
    if (!container) return;
    
    try {
        const inviti = await fetchJSON('inviti', 'GET');
        renderInviti(inviti, container);
    } catch (error) {
        container.innerHTML = `<div class="alert alert-warning">${error.message}</div>`;
    }
}

/**
 * Renderizza inviti
 */
function renderInviti(inviti, container) {
    if (!inviti.length) {
        container.innerHTML = '<div class="alert alert-info">Nessun invito pendente</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += `<thead class="table-light">
        <tr>
            <th>Attività</th>
            <th>Sala</th>
            <th>Data/Ora</th>
            <th>Organizzatore</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
    </thead><tbody>`;
    
    inviti.forEach(inv => {
        const badgeClass = `badge-${inv.risposta}`;
        const statoLabel = inv.risposta === 'attesa' ? 'In attesa' : (inv.risposta === 'si' ? 'Accettato' : 'Rifiutato');
        
        html += `
            <tr>
                <td>${inv.attivita}</td>
                <td>${inv.nome_sala} (${inv.nome_settore})</td>
                <td>${formatDateTime(inv.data_ora_inizio)}</td>
                <td>${inv.resp_nome} ${inv.resp_cognome}</td>
                <td><span class="badge ${badgeClass}">${statoLabel}</span></td>
                <td>
                    ${inv.risposta === 'attesa' ? `
                        <button class="btn btn-sm btn-success" onclick="rispondiInvito(${inv.prenotazione_id}, 'si')">
                            <i class="bi bi-check"></i> Accetta
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="showRifiutaModal(${inv.prenotazione_id})">
                            <i class="bi bi-x"></i> Rifiuta
                        </button>
                    ` : (inv.risposta === 'si' ? `
                        <button class="btn btn-sm btn-outline-danger" onclick="rimuoviPartecipazione(${inv.prenotazione_id})">
                            <i class="bi bi-x-circle"></i> Rimuovi
                        </button>
                    ` : '')}
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

/**
 * Risponde a un invito
 */
async function rispondiInvito(prenotazioneId, risposta, motivazione = '') {
    try {
        await fetchJSON('inviti/rispondi', 'POST', {
            prenotazione_id: prenotazioneId,
            risposta,
            motivazione
        });
        
        showAlert(risposta === 'si' ? 'Invito accettato!' : 'Invito rifiutato');
        loadInviti();
        loadImpegni();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/**
 * Mostra modal rifiuto
 */
function showRifiutaModal(prenotazioneId) {
    document.getElementById('rifiutaPrenotazioneId').value = prenotazioneId;
    document.getElementById('motivazioneRifiuto').value = '';
    new bootstrap.Modal(document.getElementById('rifiutaModal')).show();
}

/**
 * Conferma rifiuto
 */
function confermaRifiuto() {
    const prenotazioneId = document.getElementById('rifiutaPrenotazioneId').value;
    const motivazione = document.getElementById('motivazioneRifiuto').value;
    
    if (!motivazione.trim()) {
        showAlert('La motivazione è obbligatoria', 'warning');
        return;
    }
    
    rispondiInvito(prenotazioneId, 'no', motivazione);
    bootstrap.Modal.getInstance(document.getElementById('rifiutaModal')).hide();
}

/**
 * Rimuovi partecipazione
 */
async function rimuoviPartecipazione(prenotazioneId) {
    if (!confirm('Vuoi rimuoverti da questa prenotazione?')) return;
    
    try {
        await fetchJSON('inviti/rimuovi', 'POST', { prenotazione_id: prenotazioneId });
        showAlert('Partecipazione rimossa');
        loadInviti();
        loadImpegni();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/* ==================== IMPEGNI ==================== */

let impegniWeek = new Date();

/**
 * Carica impegni utente
 */
async function loadImpegni() {
    const container = document.getElementById('impegniContainer');
    if (!container) return;
    
    const weekStr = impegniWeek.toISOString().split('T')[0];
    
    try {
        const result = await fetchJSON('impegni', 'GET', { week: weekStr });
        
        const weekDisplay = document.getElementById('impegniWeekDisplay');
        if (weekDisplay) {
            const monday = new Date(result.settimana_inizio);
            const sunday = new Date(monday);
            sunday.setDate(sunday.getDate() + 6);
            weekDisplay.textContent = `${formatDate(monday)} - ${formatDate(sunday)}`;
        }
        
        renderImpegni(result.impegni, container);
    } catch (error) {
        container.innerHTML = `<div class="alert alert-warning">${error.message}</div>`;
    }
}

/**
 * Renderizza impegni
 */
function renderImpegni(impegni, container) {
    if (!impegni.length) {
        container.innerHTML = '<div class="alert alert-info">Nessun impegno per questa settimana</div>';
        return;
    }
    
    let html = '<div class="list-group">';
    impegni.forEach(imp => {
        html += `
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${imp.attivita}</h6>
                    <small>${formatDateTime(imp.data_ora_inizio)}</small>
                </div>
                <p class="mb-1">
                    <i class="bi bi-geo-alt"></i> ${imp.nome_sala} (${imp.nome_settore})
                    | <i class="bi bi-clock"></i> ${imp.durata}h
                </p>
                <small class="text-muted">Organizzato da ${imp.resp_nome} ${imp.resp_cognome}</small>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Navigazione settimana impegni
 */
function changeImpegniWeek(delta) {
    impegniWeek.setDate(impegniWeek.getDate() + (delta * 7));
    loadImpegni();
}

/* ==================== NUOVA PRENOTAZIONE ==================== */

/**
 * Carica sale disponibili
 */
async function loadSale() {
    const select = document.getElementById('salaSelect');
    const filter = document.getElementById('salaFilter');
    
    try {
        const sale = await fetchJSON('sale', 'GET');
        
        let options = '<option value="">Seleziona sala...</option>';
        sale.forEach(s => {
            options += `<option value="${s.id}">${s.nome_sala} (${s.nome_settore}) - ${s.capienza} posti</option>`;
        });
        
        if (select) select.innerHTML = options;
        if (filter) {
            filter.innerHTML = '<option value="">Tutte le sale</option>' + options.replace('Seleziona sala...', 'Tutte le sale');
        }
    } catch (error) {
        console.error('Errore caricamento sale:', error);
    }
}

/**
 * Carica iscritti per inviti
 */
async function loadIscritti() {
    const container = document.getElementById('iscrittiCheckboxes');
    if (!container) return;
    
    try {
        const iscritti = await fetchJSON('iscritti', 'GET');
        
        let html = '';
        iscritti.forEach(i => {
            html += `
                <div class="form-check">
                    <input class="form-check-input invitato-check" type="checkbox" value="${i.email}" id="inv_${i.email.replace('@', '_')}">
                    <label class="form-check-label" for="inv_${i.email.replace('@', '_')}">
                        ${i.nome} ${i.cognome} <small class="text-muted">(${i.ruolo})</small>
                    </label>
                </div>
            `;
        });
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<div class="alert alert-warning">Errore caricamento iscritti</div>';
    }
}

/**
 * Crea nuova prenotazione
 */
async function creaPrenotazione(event) {
    event.preventDefault();
    const form = event.target;
    
    // Raccogli invitati selezionati
    const invitati = Array.from(document.querySelectorAll('.invitato-check:checked'))
        .map(cb => cb.value);
    
    try {
        await fetchJSON('prenotazioni', 'POST', {
            sala_id: form.sala_id.value,
            data_ora_inizio: form.data_ora_inizio.value,
            durata: form.durata.value,
            attivita: form.attivita.value,
            criterio: form.criterio.value,
            invitati
        });
        
        showAlert('Prenotazione creata con successo!');
        form.reset();
        loadPrenotazioni();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

/* ==================== INIZIALIZZAZIONE ==================== */

document.addEventListener('DOMContentLoaded', () => {
    // Carica dati iniziali
    loadSale();
    loadPrenotazioni();
    loadInviti();
    loadImpegni();
    loadIscritti();
    
    // Event listener filtro sala
    const salaFilter = document.getElementById('salaFilter');
    if (salaFilter) {
        salaFilter.addEventListener('change', () => loadPrenotazioni(salaFilter.value || null));
    }
    
    // Forza minuti a 00 nel campo data/ora prenotazione
    const dataOraInput = document.querySelector('input[name="data_ora_inizio"]');
    if (dataOraInput) {
        dataOraInput.addEventListener('change', function() {
            if (this.value) {
                // Rimuove i minuti impostando sempre :00
                this.value = this.value.substring(0, 14) + '00';
            }
        });
    }
});
