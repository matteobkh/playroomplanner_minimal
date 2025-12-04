-- playroomplanner - Script SQL per creazione e popolamento database
-- NOTA: Eseguire questo script in phpMyAdmin o MySQL CLI per creare il database

-- Creazione database
CREATE DATABASE IF NOT EXISTS playroomplanner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE playroomplanner;

-- Tabella iscritti
CREATE TABLE IF NOT EXISTS iscritto (
  email VARCHAR(255) PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  cognome VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  data_nascita DATE NOT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  ruolo ENUM('docente','allievo','tecnico','responsabile') NOT NULL DEFAULT 'allievo',
  data_inizio_responsabile DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella settori
CREATE TABLE IF NOT EXISTS settore (
  nome_settore VARCHAR(100) PRIMARY KEY,
  num_iscritti INT NOT NULL DEFAULT 0,
  responsabile_email VARCHAR(255) NOT NULL,
  FOREIGN KEY (responsabile_email) REFERENCES iscritto(email)
);

-- Tabella sale
CREATE TABLE IF NOT EXISTS sala (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_sala VARCHAR(150) NOT NULL,
  nome_settore VARCHAR(100) NOT NULL,
  capienza INT NOT NULL CHECK (capienza > 0),
  UNIQUE(nome_sala, nome_settore),
  FOREIGN KEY (nome_settore) REFERENCES settore(nome_settore)
);

-- Tabella dotazioni
CREATE TABLE IF NOT EXISTS dotazione (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_dotazione VARCHAR(150) NOT NULL,
  nome_settore VARCHAR(100) NOT NULL,
  nome_sala_id INT NOT NULL,
  FOREIGN KEY (nome_settore) REFERENCES settore(nome_settore),
  FOREIGN KEY (nome_sala_id) REFERENCES sala(id)
);

-- Tabella prenotazioni
CREATE TABLE IF NOT EXISTS prenotazione (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data_ora_inizio DATETIME NOT NULL,
  durata INT NOT NULL CHECK (durata > 0),
  attivita VARCHAR(255) NOT NULL,
  num_iscritti INT NOT NULL DEFAULT 0,
  criterio ENUM('tutti','settore','ruolo','selezione') NOT NULL DEFAULT 'selezione',
  nome_settore VARCHAR(100) NOT NULL,
  sala_id INT NOT NULL,
  responsabile_email VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nome_settore) REFERENCES settore(nome_settore),
  FOREIGN KEY (sala_id) REFERENCES sala(id),
  FOREIGN KEY (responsabile_email) REFERENCES iscritto(email)
);

-- Tabella inviti
CREATE TABLE IF NOT EXISTS invito (
  iscritto_email VARCHAR(255) NOT NULL,
  prenotazione_id INT NOT NULL,
  data_ora_risposta DATETIME DEFAULT NULL,
  risposta ENUM('si','no','attesa') NOT NULL DEFAULT 'attesa',
  motivazione TEXT DEFAULT NULL,
  PRIMARY KEY (iscritto_email, prenotazione_id),
  FOREIGN KEY (iscritto_email) REFERENCES iscritto(email),
  FOREIGN KEY (prenotazione_id) REFERENCES prenotazione(id)
);

-- Indici per ottimizzazione
CREATE INDEX idx_preno_sala ON prenotazione(sala_id, data_ora_inizio);
CREATE INDEX idx_preno_resp ON prenotazione(responsabile_email);

-- =====================================================
-- DATI DI ESEMPIO
-- =====================================================

-- Inserimento iscritti (password in chiaro per demo)
INSERT INTO iscritto (email, nome, cognome, password, data_nascita, ruolo, data_inizio_responsabile) VALUES
('resp.musica@example.com', 'Marco', 'Rossi', 'password', '1980-05-15', 'responsabile', '2020-01-01'),
('resp.teatro@example.com', 'Laura', 'Bianchi', 'password', '1985-08-22', 'responsabile', '2019-06-01'),
('resp.ballo@example.com', 'Giuseppe', 'Verdi', 'password', '1978-03-10', 'responsabile', '2021-03-15'),
('docente1@example.com', 'Anna', 'Ferrari', 'password', '1975-11-30', 'docente', NULL),
('docente2@example.com', 'Paolo', 'Romano', 'password', '1982-07-18', 'docente', NULL),
('allievo1@example.com', 'Giulia', 'Costa', 'password', '2000-02-14', 'allievo', NULL),
('allievo2@example.com', 'Luca', 'Marino', 'password', '1998-09-25', 'allievo', NULL),
('allievo3@example.com', 'Sara', 'Galli', 'password', '2001-04-08', 'allievo', NULL),
('tecnico1@example.com', 'Roberto', 'Colombo', 'password', '1990-12-01', 'tecnico', NULL),
('tecnico2@example.com', 'Elena', 'Ricci', 'password', '1988-06-20', 'tecnico', NULL);

-- Inserimento settori
INSERT INTO settore (nome_settore, num_iscritti, responsabile_email) VALUES
('Musica', 15, 'resp.musica@example.com'),
('Teatro', 12, 'resp.teatro@example.com'),
('Ballo', 10, 'resp.ballo@example.com');

-- Inserimento sale
INSERT INTO sala (nome_sala, nome_settore, capienza) VALUES
('Sala Beethoven', 'Musica', 20),
('Sala Mozart', 'Musica', 15),
('Studio Recording', 'Musica', 5),
('Teatro Grande', 'Teatro', 50),
('Sala Prove Attori', 'Teatro', 12),
('Sala Danza 1', 'Ballo', 25),
('Sala Danza 2', 'Ballo', 15);

-- Inserimento dotazioni
INSERT INTO dotazione (nome_dotazione, nome_settore, nome_sala_id) VALUES
('Pianoforte a coda', 'Musica', 1),
('Impianto audio professionale', 'Musica', 1),
('Batteria acustica', 'Musica', 2),
('Mixer 24 canali', 'Musica', 3),
('Microfoni condensatore', 'Musica', 3),
('Palcoscenico mobile', 'Teatro', 4),
('Luci sceniche', 'Teatro', 4),
('Specchi parete', 'Ballo', 6),
('Sbarre danza', 'Ballo', 6),
('Pavimento antiurto', 'Ballo', 7);

-- Inserimento prenotazioni di esempio
INSERT INTO prenotazione (data_ora_inizio, durata, attivita, num_iscritti, criterio, nome_settore, sala_id, responsabile_email) VALUES
(DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, 2, 'Prove orchestra giovanile', 0, 'settore', 'Musica', 1, 'resp.musica@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 14 HOUR, 3, 'Lezione canto corale', 0, 'selezione', 'Musica', 2, 'resp.musica@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 16 HOUR, 2, 'Prove spettacolo estivo', 0, 'tutti', 'Teatro', 4, 'resp.teatro@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 18 HOUR, 2, 'Corso tango principianti', 0, 'ruolo', 'Ballo', 6, 'resp.ballo@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 11 HOUR, 1, 'Registrazione demo band', 0, 'selezione', 'Musica', 3, 'resp.musica@example.com');

-- Inserimento inviti di esempio
INSERT INTO invito (iscritto_email, prenotazione_id, risposta) VALUES
('allievo1@example.com', 1, 'attesa'),
('allievo2@example.com', 1, 'attesa'),
('docente1@example.com', 1, 'attesa'),
('allievo1@example.com', 2, 'attesa'),
('allievo3@example.com', 2, 'attesa'),
('docente1@example.com', 3, 'attesa'),
('docente2@example.com', 3, 'attesa'),
('allievo2@example.com', 3, 'attesa'),
('allievo3@example.com', 4, 'attesa'),
('tecnico1@example.com', 5, 'attesa'),
('allievo1@example.com', 5, 'attesa');

-- =====================================================
-- CREDENZIALI DI TEST
-- =====================================================
-- Email: resp.musica@example.com  | Password: password (Responsabile)
-- Email: resp.teatro@example.com  | Password: password (Responsabile)
-- Email: resp.ballo@example.com   | Password: password (Responsabile)
-- Email: allievo1@example.com     | Password: password (Allievo)
-- Email: docente1@example.com     | Password: password (Docente)
-- Email: tecnico1@example.com     | Password: password (Tecnico)
