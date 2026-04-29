-- ============================================================
-- Schema database Biblioteca ITIS  v1.0
-- Importa questo file, poi esegui seed.php per i dati demo
-- ============================================================

CREATE DATABASE IF NOT EXISTS biblioteca
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca;

CREATE TABLE IF NOT EXISTS libri (
    id                INT           AUTO_INCREMENT PRIMARY KEY,
    titolo            VARCHAR(255)  NOT NULL,
    autore            VARCHAR(255)  NOT NULL,
    categoria         VARCHAR(100)  NOT NULL DEFAULT '',
    copie_totali      INT           NOT NULL DEFAULT 1,
    copie_disponibili INT           NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lettori (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(255)  NOT NULL,
    email           VARCHAR(255)  NOT NULL UNIQUE,
    password        VARCHAR(255)  NOT NULL,
    ruolo           ENUM('lettore','bibliotecario','admin') NOT NULL DEFAULT 'lettore',
    data_iscrizione DATE          NOT NULL DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestiti (
    id                INT           AUTO_INCREMENT PRIMARY KEY,
    id_lettore        INT           NOT NULL,
    id_libro          INT           NOT NULL,
    data_inizio       DATE          NOT NULL,
    data_scadenza     DATE          NOT NULL,
    data_restituzione DATE          DEFAULT NULL,
    multa             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    rinnovi           TINYINT       NOT NULL DEFAULT 0,
    FOREIGN KEY (id_lettore) REFERENCES lettori(id) ON DELETE CASCADE,
    FOREIGN KEY (id_libro)   REFERENCES libri(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_prestiti_lettore  ON prestiti (id_lettore);
CREATE INDEX IF NOT EXISTS idx_prestiti_libro    ON prestiti (id_libro);
CREATE INDEX IF NOT EXISTS idx_prestiti_scadenza ON prestiti (data_scadenza);
