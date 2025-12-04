<?php
/**
 * nav.php - Barra di navigazione dinamica
 * Mostra menu diversi in base allo stato di login e ruolo utente
 */
$user = $_SESSION['user'] ?? null;
$isResponsabile = $user && $user['ruolo'] === 'responsabile' && !empty($user['data_inizio_responsabile']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-music-note-beamed"></i> Play Room Planner
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php#sale">Sale Prove</a>
                </li>
                <?php if ($user): ?>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#inviti">I Miei Inviti</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#impegni">I Miei Impegni</a>
                </li>
                <?php if ($isResponsabile): ?>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#nuova-prenotazione">Nuova Prenotazione</a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?>
                        <?php if ($isResponsabile): ?>
                        <span class="badge bg-warning text-dark">Responsabile</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="showProfilo()">Profilo</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#registerModal">
                        <i class="bi bi-person-plus"></i> Registrati
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
