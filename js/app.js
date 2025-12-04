/**
 * app.js - Logica frontend Play Room Planner
 */

/* === UTILITY === */

// Chiamata API generica con gestione errori
async function api(action, method = 'GET', data = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    
    const url = method === 'GET' && data 
        ? `api.php?action=${action}&${new URLSearchParams(data)}`
        : `api.php?action=${action}`;
    
    const res = await fetch(url, opts);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);
    return json.data;
}

// Mostra alert Bootstrap temporaneo
function showAlert(msg, type = 'success') {
    const c = document.getElementById('alertContainer');
    if (!c) return;
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    c.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}

// Formatta data/ora in italiano
function fmtDateTime(s) {
    return new Date(s).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

// Formatta solo data
function fmtDate(s) {
    return new Date(s).toLocaleDateString('it-IT', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
}

/* === AUTENTICAZIONE === */

// Login utente
async function login(e) {
    e.preventDefault();
    try {
        await api('login', 'POST', { email: e.target.email.value, password: e.target.password.value });
        showAlert('Login effettuato!');
        setTimeout(() => location.reload(), 1000);
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Registrazione nuovo utente
async function register(e) {
    e.preventDefault();
    const f = e.target;
    try {
        await api('register', 'POST', {
            email: f.email.value, password: f.password.value,
            nome: f.nome.value, cognome: f.cognome.value,
            data_nascita: f.data_nascita.value, ruolo: f.ruolo.value
        });
        showAlert('Registrazione completata! Effettua il login.');
        bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Logout
async function logout() {
    await api('logout', 'POST');
    location.reload();
}

// Mostra modal profilo
function showProfilo() {
    new bootstrap.Modal(document.getElementById('profiloModal')).show();
}

// Salva modifiche profilo
async function salvaProfilo(e) {
    e.preventDefault();
    try {
        await api('profilo', 'PUT', {
            nome: e.target.nome.value,
            cognome: e.target.cognome.value,
            data_nascita: e.target.data_nascita.value
        });
        showAlert('Profilo aggiornato!');
        setTimeout(() => location.reload(), 1000);
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Cancella account con doppia conferma
async function cancellaProfilo() {
    if (!confirm('Sei sicuro di voler cancellare il tuo account?')) return;
    if (!confirm('ATTENZIONE: Tutti i tuoi dati verranno eliminati. Confermi?')) return;
    try {
        await api('profilo', 'DELETE');
        showAlert('Account cancellato');
        setTimeout(() => location.reload(), 1000);
    } catch (err) { showAlert(err.message, 'danger'); }
}

/* === PRENOTAZIONI === */

let currentWeek = new Date();  // Settimana visualizzata
let prenotazioniCache = [];    // Cache per modifica/cancella

// Carica e mostra prenotazioni settimanali
async function loadPrenotazioni(salaId = null) {
    const container = document.getElementById('prenotazioniContainer');
    if (!container) return;
    
    try {
        const params = { week: currentWeek.toISOString().split('T')[0] };
        if (salaId) params.sala_id = salaId;
        
        const res = await api('prenotazioni', 'GET', params);
        prenotazioniCache = res.prenotazioni;
        
        // Aggiorna label settimana
        const wd = document.getElementById('weekDisplay');
        if (wd) {
            const lun = new Date(res.settimana_inizio);
            const dom = new Date(lun); dom.setDate(dom.getDate() + 6);
            wd.textContent = `${fmtDate(lun)} - ${fmtDate(dom)}`;
        }
        
        // Renderizza cards
        const userEmail = document.body.dataset.userEmail || '';
        if (!res.prenotazioni.length) {
            container.innerHTML = '<div class="alert alert-info">Nessuna prenotazione questa settimana</div>';
            return;
        }
        
        container.innerHTML = '<div class="row">' + res.prenotazioni.map(p => {
            const isOwner = userEmail && p.responsabile_email === userEmail;
            return `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card prenotazione-card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <span><strong>${p.nome_sala}</strong> - ${p.nome_settore}</span>
                            ${isOwner ? `<div class="btn-group btn-group-sm">
                                <button class="btn btn-light btn-sm" onclick="showModificaPrenotazione(${p.id})" title="Modifica"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="cancellaPrenotazione(${p.id})" title="Cancella"><i class="bi bi-trash"></i></button>
                            </div>` : ''}
                        </div>
                        <div class="card-body">
                            <h6>${p.attivita}</h6>
                            <p class="mb-1"><i class="bi bi-calendar"></i> ${fmtDateTime(p.data_ora_inizio)}</p>
                            <p class="mb-1"><i class="bi bi-clock"></i> ${p.durata}h</p>
                            <p class="mb-1"><i class="bi bi-people"></i> ${p.num_partecipanti}/${p.capienza}</p>
                            <p class="mb-0"><i class="bi bi-person"></i> ${p.resp_nome} ${p.resp_cognome}</p>
                        </div>
                    </div>
                </div>`;
        }).join('') + '</div>';
    } catch (err) { container.innerHTML = `<div class="alert alert-danger">${err.message}</div>`; }
}

// Cambia settimana visualizzata
function changeWeek(delta) {
    currentWeek.setDate(currentWeek.getDate() + delta * 7);
    loadPrenotazioni(document.getElementById('salaFilter')?.value || null);
}

// Cancella prenotazione con conferma
async function cancellaPrenotazione(id) {
    if (!confirm('Cancellare questa prenotazione?')) return;
    try {
        await api('prenotazioni&id=' + id, 'DELETE');
        showAlert('Prenotazione cancellata!');
        loadPrenotazioni();
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Mostra modal modifica prenotazione
async function showModificaPrenotazione(id) {
    const p = prenotazioniCache.find(x => x.id == id);
    if (!p) return showAlert('Prenotazione non trovata', 'danger');
    
    // Popola form
    document.getElementById('modificaId').value = p.id;
    document.getElementById('modificaDataOra').value = p.data_ora_inizio.replace(' ', 'T').substring(0, 16);
    document.getElementById('modificaDurata').value = p.durata;
    document.getElementById('modificaAttivita').value = p.attivita;
    
    // Carica checkbox invitati
    await loadIscrittiModifica(id);
    new bootstrap.Modal(document.getElementById('modificaPrenotazioneModal')).show();
}

// Carica iscritti con invitati pre-selezionati
async function loadIscrittiModifica(prenotazioneId) {
    const container = document.getElementById('modificaIscrittiCheckboxes');
    try {
        const [iscritti, invitati] = await Promise.all([
            api('iscritti', 'GET'),
            api('invitati&prenotazione_id=' + prenotazioneId, 'GET')
        ]);
        const emails = invitati.map(i => i.iscritto_email);
        
        container.innerHTML = iscritti.map(i => `
            <div class="form-check">
                <input class="form-check-input mod-inv" type="checkbox" value="${i.email}" ${emails.includes(i.email) ? 'checked' : ''}>
                <label class="form-check-label">${i.nome} ${i.cognome} <small class="text-muted">(${i.ruolo})</small></label>
            </div>
        `).join('');
    } catch (err) { container.innerHTML = '<div class="alert alert-warning">Errore</div>'; }
}

// Salva modifiche prenotazione
async function salvaModificaPrenotazione() {
    const invitati = [...document.querySelectorAll('.mod-inv:checked')].map(c => c.value);
    try {
        await api('prenotazioni', 'PUT', {
            id: document.getElementById('modificaId').value,
            data_ora_inizio: document.getElementById('modificaDataOra').value,
            durata: document.getElementById('modificaDurata').value,
            attivita: document.getElementById('modificaAttivita').value,
            invitati
        });
        showAlert('Prenotazione modificata!');
        bootstrap.Modal.getInstance(document.getElementById('modificaPrenotazioneModal')).hide();
        loadPrenotazioni();
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Crea nuova prenotazione
async function creaPrenotazione(e) {
    e.preventDefault();
    const f = e.target;
    const invitati = [...document.querySelectorAll('.invitato-check:checked')].map(c => c.value);
    try {
        await api('prenotazioni', 'POST', {
            sala_id: f.sala_id.value,
            data_ora_inizio: f.data_ora_inizio.value,
            durata: f.durata.value,
            attivita: f.attivita.value,
            invitati
        });
        showAlert('Prenotazione creata!');
        f.reset();
        loadPrenotazioni();
    } catch (err) { showAlert(err.message, 'danger'); }
}

/* === INVITI === */

// Carica inviti utente
async function loadInviti() {
    const container = document.getElementById('invitiContainer');
    if (!container) return;
    
    try {
        const inviti = await api('inviti', 'GET');
        if (!inviti.length) {
            container.innerHTML = '<div class="alert alert-info">Nessun invito</div>';
            return;
        }
        
        container.innerHTML = `<div class="table-responsive"><table class="table table-hover">
            <thead class="table-light"><tr><th>Attività</th><th>Sala</th><th>Data/Ora</th><th>Organizzatore</th><th>Stato</th><th>Azioni</th></tr></thead>
            <tbody>${inviti.map(i => `<tr>
                <td>${i.attivita}</td>
                <td>${i.nome_sala} (${i.nome_settore})</td>
                <td>${fmtDateTime(i.data_ora_inizio)}</td>
                <td>${i.resp_nome} ${i.resp_cognome}</td>
                <td><span class="badge badge-${i.risposta}">${i.risposta === 'attesa' ? 'In attesa' : i.risposta === 'si' ? 'Accettato' : 'Rifiutato'}</span></td>
                <td>${i.risposta === 'attesa' ? `
                    <button class="btn btn-sm btn-success" onclick="rispondiInvito(${i.prenotazione_id},'si')"><i class="bi bi-check"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="showRifiutaModal(${i.prenotazione_id})"><i class="bi bi-x"></i></button>
                ` : i.risposta === 'si' ? `
                    <button class="btn btn-sm btn-outline-danger" onclick="rimuoviPartecipazione(${i.prenotazione_id})"><i class="bi bi-x-circle"></i></button>
                ` : ''}</td>
            </tr>`).join('')}</tbody></table></div>`;
    } catch (err) { container.innerHTML = `<div class="alert alert-warning">${err.message}</div>`; }
}

// Rispondi a invito
async function rispondiInvito(id, risposta, motivazione = '') {
    try {
        await api('inviti/rispondi', 'POST', { prenotazione_id: id, risposta, motivazione });
        showAlert(risposta === 'si' ? 'Accettato!' : 'Rifiutato');
        loadInviti();
        loadImpegni();
    } catch (err) { showAlert(err.message, 'danger'); }
}

// Mostra modal rifiuto
function showRifiutaModal(id) {
    document.getElementById('rifiutaPrenotazioneId').value = id;
    document.getElementById('motivazioneRifiuto').value = '';
    new bootstrap.Modal(document.getElementById('rifiutaModal')).show();
}

// Conferma rifiuto con motivazione
function confermaRifiuto() {
    const id = document.getElementById('rifiutaPrenotazioneId').value;
    const mot = document.getElementById('motivazioneRifiuto').value;
    if (!mot.trim()) return showAlert('Motivazione obbligatoria', 'warning');
    rispondiInvito(id, 'no', mot);
    bootstrap.Modal.getInstance(document.getElementById('rifiutaModal')).hide();
}

// Rimuovi partecipazione
async function rimuoviPartecipazione(id) {
    if (!confirm('Rimuoverti da questa prenotazione?')) return;
    try {
        await api('inviti/rimuovi', 'POST', { prenotazione_id: id });
        showAlert('Rimosso');
        loadInviti();
        loadImpegni();
    } catch (err) { showAlert(err.message, 'danger'); }
}

/* === IMPEGNI === */

let impegniWeek = new Date();

// Carica impegni settimanali
async function loadImpegni() {
    const container = document.getElementById('impegniContainer');
    if (!container) return;
    
    try {
        const res = await api('impegni', 'GET', { week: impegniWeek.toISOString().split('T')[0] });
        
        const wd = document.getElementById('impegniWeekDisplay');
        if (wd) {
            const lun = new Date(res.settimana_inizio);
            const dom = new Date(lun); dom.setDate(dom.getDate() + 6);
            wd.textContent = `${fmtDate(lun)} - ${fmtDate(dom)}`;
        }
        
        if (!res.impegni.length) {
            container.innerHTML = '<div class="alert alert-info">Nessun impegno</div>';
            return;
        }
        
        container.innerHTML = '<div class="list-group">' + res.impegni.map(i => `
            <div class="list-group-item">
                <div class="d-flex justify-content-between"><h6>${i.attivita}</h6><small>${fmtDateTime(i.data_ora_inizio)}</small></div>
                <p class="mb-1"><i class="bi bi-geo-alt"></i> ${i.nome_sala} | <i class="bi bi-clock"></i> ${i.durata}h</p>
                <small class="text-muted">Org: ${i.resp_nome} ${i.resp_cognome}</small>
            </div>
        `).join('') + '</div>';
    } catch (err) { container.innerHTML = `<div class="alert alert-warning">${err.message}</div>`; }
}

// Cambia settimana impegni
function changeImpegniWeek(delta) {
    impegniWeek.setDate(impegniWeek.getDate() + delta * 7);
    loadImpegni();
}

/* === DATI AUSILIARI === */

// Carica select sale
async function loadSale() {
    try {
        const sale = await api('sale', 'GET');
        const opts = '<option value="">Tutte le sale</option>' + sale.map(s => 
            `<option value="${s.id}">${s.nome_sala} (${s.nome_settore}) - ${s.capienza} posti</option>`
        ).join('');
        
        const sel = document.getElementById('salaSelect');
        const flt = document.getElementById('salaFilter');
        if (sel) sel.innerHTML = opts.replace('Tutte le sale', 'Seleziona sala...');
        if (flt) flt.innerHTML = opts;
    } catch (err) { console.error(err); }
}

// Carica checkbox iscritti per nuova prenotazione
async function loadIscritti() {
    const container = document.getElementById('iscrittiCheckboxes');
    if (!container) return;
    
    try {
        const iscritti = await api('iscritti', 'GET');
        container.innerHTML = iscritti.map(i => `
            <div class="form-check">
                <input class="form-check-input invitato-check" type="checkbox" value="${i.email}">
                <label class="form-check-label">${i.nome} ${i.cognome} <small class="text-muted">(${i.ruolo})</small></label>
            </div>
        `).join('');
    } catch (err) { container.innerHTML = '<div class="alert alert-warning">Errore</div>'; }
}

/* === INIZIALIZZAZIONE === */

document.addEventListener('DOMContentLoaded', () => {
    loadSale();
    loadPrenotazioni();
    loadInviti();
    loadImpegni();
    loadIscritti();
    
    // Filtro sala
    document.getElementById('salaFilter')?.addEventListener('change', e => loadPrenotazioni(e.target.value || null));
    
    // Forza minuti a 00 nei datetime-local
    document.querySelectorAll('input[type="datetime-local"]').forEach(el => {
        el.addEventListener('change', function() { if (this.value) this.value = this.value.substring(0, 14) + '00'; });
    });
});
