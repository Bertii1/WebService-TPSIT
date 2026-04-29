# Biblioteca ITIS — Sistema di Gestione Prestiti

Web service PHP per la gestione della biblioteca scolastica.

## Funzionalità

- Catalogo libri con ricerca per titolo/autore e filtro categoria
- Disponibilità copie in tempo reale
- Login per studenti, bibliotecari e admin
- Profilo studente: prestiti attivi, scadenze, rinnovo online (max 2 volte)
- Dashboard bibliotecario: nuovo prestito, registrazione resi, calcolo multa (€0,50/giorno)
- Pannello admin: gestione catalogo e utenti
- Cron job per email di promemoria 3 giorni prima della scadenza

## Prerequisiti

- XAMPP (PHP 7.4+, MySQL/MariaDB 5.7+)
- Browser moderno (Chrome, Firefox, Edge)

## Installazione

### 1. Clona il repository

```bash
git clone https://github.com/tuo-utente/biblioteca-itis.git
cd biblioteca-itis
```

Oppure copia la cartella in `C:\xampp\htdocs\` (Windows) o `/opt/lampp/htdocs/` (Linux).

### 2. Crea il database

Apri **phpMyAdmin** (`http://localhost/phpmyadmin`) e:

1. Crea un nuovo database `biblioteca` con collation `utf8mb4_unicode_ci`
2. Seleziona il database → scheda **Importa** → carica `biblioteca.sql` → Esegui

Oppure da terminale:

```bash
mysql -u root -p -e "CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p biblioteca < biblioteca.sql
```

### 3. Configura la connessione

Modifica `includes/env.php`:

```php
$hostname    = "localhost";
$db_username = "root";      // tuo utente MySQL
$db_password = "";          // tua password MySQL
$db_name     = "biblioteca";
```

### 4. Inserisci i dati demo

Apri nel browser:

```
http://localhost/biblioteca-itis/seed.php
```

**Elimina o rinomina `seed.php` dopo l'esecuzione.**

### 5. Avvia

```
http://localhost/biblioteca-itis/
```

## Credenziali demo

| Ruolo          | Email                         | Password     |
|----------------|-------------------------------|--------------|
| Admin          | admin@biblioteca.it           | admin123     |
| Bibliotecario  | bibliotecario@biblioteca.it   | biblio123    |
| Studente       | giovanni@studenti.it          | studente123  |
| Studente       | lucia@studenti.it             | studente123  |

## Struttura cartelle

```
biblioteca-itis/
├── index.php              Homepage con statistiche
├── login.php              Accesso utenti
├── logout.php             Disconnessione
├── catalogo.php           Ricerca e lista libri (R1–R3)
├── profilo.php            Prestiti studente + rinnovo (R8–R10)
├── dashboard.php          Gestione prestiti/resi (R4–R7)
├── admin.php              Gestione catalogo e utenti
├── seed.php               Inserimento dati demo (da eliminare dopo)
├── biblioteca.sql         Schema database
├── includes/
│   ├── auth.php           Funzioni sessione e controllo accesso
│   ├── db.php             Connessione MySQL
│   ├── env.php            Credenziali database
│   ├── header.php         Header HTML + navbar Bootstrap
│   └── footer.php         Footer HTML
├── css/
│   └── style.css          Stili custom
├── js/
│   └── script.js          JS (conferma eliminazione)
└── cron/
    └── notifiche.php      Email promemoria scadenze (R11)
```

## Notifiche email (cron)

Per attivare le email automatiche 3 giorni prima della scadenza, configura un cron job:

```bash
# Invia ogni giorno alle 08:00
0 8 * * * php /path/to/biblioteca-itis/cron/notifiche.php >> /var/log/biblioteca_cron.log 2>&1
```

Su XAMPP locale il cron non è attivo: esegui `cron/notifiche.php` manualmente per testare.

## Tecnologie

- **Backend:** PHP 7.4+, MySQL 5.7+
- **Frontend:** HTML5, Bootstrap 5.3, JavaScript vanilla
- **Sicurezza:** password bcrypt (`password_hash`), prepared statements, session-based auth

## Requisiti implementati

| ID  | Descrizione                                      | File              |
|-----|--------------------------------------------------|-------------------|
| R1  | Lista libri con titolo, autore, categoria        | `catalogo.php`    |
| R2  | Ricerca per titolo o autore                      | `catalogo.php`    |
| R3  | Disponibilità copie                              | `catalogo.php`    |
| R4  | Registrazione nuovo prestito                     | `dashboard.php`   |
| R5  | Registrazione restituzione                       | `dashboard.php`   |
| R6  | Lista prestiti aperti                            | `dashboard.php`   |
| R7  | Calcolo multa €0,50/giorno di ritardo            | `dashboard.php`   |
| R8  | Login studente con email/password                | `login.php`       |
| R9  | Visualizzazione prestiti attivi e scadenze       | `profilo.php`     |
| R10 | Rinnovo prestito (+30 giorni, max 2 volte)       | `profilo.php`     |
| R11 | Email promemoria 3 giorni prima scadenza         | `cron/notifiche.php` |
