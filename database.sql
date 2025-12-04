-- Play Room Planner - Database
CREATE DATABASE IF NOT EXISTS playroomplanner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE playroomplanner;

-- Tabelle
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

CREATE TABLE IF NOT EXISTS settore (
  nome_settore VARCHAR(100) PRIMARY KEY,
  num_iscritti INT NOT NULL DEFAULT 0,
  responsabile_email VARCHAR(255) NOT NULL,
  FOREIGN KEY (responsabile_email) REFERENCES iscritto(email)
);

CREATE TABLE IF NOT EXISTS sala (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_sala VARCHAR(150) NOT NULL,
  nome_settore VARCHAR(100) NOT NULL,
  capienza INT NOT NULL CHECK (capienza > 0),
  UNIQUE(nome_sala, nome_settore),
  FOREIGN KEY (nome_settore) REFERENCES settore(nome_settore)
);

CREATE TABLE IF NOT EXISTS dotazione (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_dotazione VARCHAR(150) NOT NULL,
  nome_settore VARCHAR(100) NOT NULL,
  nome_sala_id INT NOT NULL,
  FOREIGN KEY (nome_settore) REFERENCES settore(nome_settore),
  FOREIGN KEY (nome_sala_id) REFERENCES sala(id)
);

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

CREATE INDEX idx_preno_sala ON prenotazione(sala_id, data_ora_inizio);
CREATE INDEX idx_preno_resp ON prenotazione(responsabile_email);

-- Dati di esempio (password: password123)
INSERT INTO iscritto (email, nome, cognome, password, data_nascita, ruolo, data_inizio_responsabile) VALUES
('resp.musica@example.com', 'Marco', 'Rossi', 'password123', '1980-05-15', 'responsabile', '2020-01-01'),
('resp.teatro@example.com', 'Laura', 'Bianchi', 'password123', '1985-08-22', 'responsabile', '2019-06-01'),
('resp.ballo@example.com', 'Giuseppe', 'Verdi', 'password123', '1978-03-10', 'responsabile', '2021-03-15'),
('docente1@example.com', 'Anna', 'Ferrari', 'password123', '1975-11-30', 'docente', NULL),
('allievo1@example.com', 'Giulia', 'Costa', 'password123', '2000-02-14', 'allievo', NULL),
('allievo2@example.com', 'Luca', 'Marino', 'password123', '1998-09-25', 'allievo', NULL),
('tecnico1@example.com', 'Roberto', 'Colombo', 'password123', '1990-12-01', 'tecnico', NULL);

INSERT INTO settore (nome_settore, num_iscritti, responsabile_email) VALUES
('Musica', 15, 'resp.musica@example.com'),
('Teatro', 12, 'resp.teatro@example.com'),
('Ballo', 10, 'resp.ballo@example.com');

INSERT INTO sala (nome_sala, nome_settore, capienza) VALUES
('Sala Beethoven', 'Musica', 20),
('Sala Mozart', 'Musica', 15),
('Teatro Grande', 'Teatro', 50),
('Sala Danza', 'Ballo', 25);

INSERT INTO prenotazione (data_ora_inizio, durata, attivita, nome_settore, sala_id, responsabile_email) VALUES
(DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, 2, 'Prove orchestra', 'Musica', 1, 'resp.musica@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 14 HOUR, 3, 'Lezione canto', 'Musica', 2, 'resp.musica@example.com'),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 16 HOUR, 2, 'Prove spettacolo', 'Teatro', 3, 'resp.teatro@example.com');

INSERT INTO invito (iscritto_email, prenotazione_id) VALUES
('allievo1@example.com', 1), ('allievo2@example.com', 1), ('docente1@example.com', 1),
('allievo1@example.com', 2), ('docente1@example.com', 3), ('allievo2@example.com', 3);

-- Credenziali test: email + password123
